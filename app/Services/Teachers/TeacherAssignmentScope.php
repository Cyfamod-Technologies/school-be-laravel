<?php

namespace App\Services\Teachers;

use App\Models\ClassTeacher;
use App\Models\Staff;
use App\Models\Student;
use App\Models\SubjectTeacherAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TeacherAssignmentScope
{
    public function __construct(
        private bool $isTeacher,
        private ?Staff $staff,
        private Collection $subjectAssignments,
        private Collection $classAssignments,
    ) {
    }

    public static function forNonTeacher(): self
    {
        return new self(false, null, collect(), collect());
    }

    public function isTeacher(): bool
    {
        return $this->isTeacher;
    }

    public function staff(): ?Staff
    {
        return $this->staff;
    }

    /**
     * @return Collection<int, SubjectTeacherAssignment>
     */
    public function subjectAssignments(): Collection
    {
        return $this->subjectAssignments;
    }

    /**
     * @return Collection<int, ClassTeacher>
     */
    public function classAssignments(): Collection
    {
        return $this->classAssignments;
    }

    public function restrictStudentQuery(Builder $builder): void
    {
        if (! $this->isTeacher) {
            return;
        }

        $contexts = $this->studentContexts();

        if ($contexts->isEmpty()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where(function (Builder $outer) use ($contexts) {
            foreach ($contexts as $context) {
                $outer->orWhere(function (Builder $clause) use ($context) {
                    $clause->where('school_class_id', $context['school_class_id']);

                    if ($context['class_arm_id']) {
                        $clause->where('class_arm_id', $context['class_arm_id']);
                    }

                    if ($context['class_section_id']) {
                        $clause->where('class_section_id', $context['class_section_id']);
                    }
                });
            }
        });
    }

    public function restrictAttendanceQuery(Builder $builder): void
    {
        $this->restrictStudentQuery($builder);
    }

    public function restrictResultQuery(Builder $builder): void
    {
        if (! $this->isTeacher) {
            return;
        }

        $assignments = $this->subjectAssignments
            ->filter(fn (SubjectTeacherAssignment $assignment) => $assignment->subject_id && $assignment->school_class_id);

        $studentContexts = $this->studentContexts();

        if ($assignments->isEmpty() && $studentContexts->isEmpty()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where(function (Builder $outer) use ($assignments, $studentContexts) {
            foreach ($assignments as $assignment) {
                $outer->orWhere(function (Builder $clause) use ($assignment) {
                    $clause->where('subject_id', $assignment->subject_id);

                    if ($assignment->session_id) {
                        $clause->where('session_id', $assignment->session_id);
                    }

                    if ($assignment->term_id) {
                        $clause->where('term_id', $assignment->term_id);
                    }

                    $clause->whereHas('student', function (Builder $studentQuery) use ($assignment) {
                        $studentQuery->where('school_class_id', $assignment->school_class_id);

                        if ($assignment->class_arm_id) {
                            $studentQuery->where('class_arm_id', $assignment->class_arm_id);
                        }

                        if ($assignment->class_section_id) {
                            $studentQuery->where('class_section_id', $assignment->class_section_id);
                        }
                    });
                });
            }

            // Allow class teachers to view results for students
            // in their assigned classes/arms/sections, regardless of subject.
            foreach ($studentContexts as $context) {
                $outer->orWhereHas('student', function (Builder $studentQuery) use ($context) {
                    $studentQuery->where('school_class_id', $context['school_class_id']);

                    if ($context['class_arm_id']) {
                        $studentQuery->where('class_arm_id', $context['class_arm_id']);
                    }

                    if ($context['class_section_id']) {
                        $studentQuery->where('class_section_id', $context['class_section_id']);
                    }
                });
            }
        });
    }

    public function allowsStudent(Student $student): bool
    {
        if (! $this->isTeacher) {
            return true;
        }

        return $this->studentContexts()->contains(
            fn (array $context) => $this->matchesContext($context, $student),
        );
    }

    public function allowsStudentSubject(
        Student $student,
        string $subjectId,
        ?string $sessionId = null,
        ?string $termId = null,
        ?string $assessmentComponentId = null
    ): bool {
        if (! $this->isTeacher) {
            return true;
        }

        // If the teacher has no recorded subject or class assignments,
        // do not restrict result entry at all.
        if ($this->subjectAssignments->isEmpty() && $this->classAssignments->isEmpty()) {
            return true;
        }

        // Relaxed rule: as long as the teacher is allowed to manage
        // this student (based on class/arm/section context), allow
        // recording results for any subject/session/term.
        return $this->allowsStudent($student);
    }

    /**
     * @return Collection<int, array{
     *     context_key: string,
     *     class: array|null,
     *     class_arm: array|null,
     *     class_section: array|null,
     *     session: array|null,
     *     term: array|null,
     *     subjects: array<int, array{id: string, name: ?string, code: ?string}>
     * }>
     */
    public function summarizeAssignments(): Collection
    {
        if (! $this->isTeacher) {
            return collect();
        }

        $entries = [];

        $registerAssignment = function ($assignment, bool $includeSubjects = false, bool $isClassTeacher = false) use (&$entries): void {
            if (! $assignment->school_class_id) {
                return;
            }

            $key = $this->contextKey(
                $assignment->school_class_id,
                $assignment->class_arm_id ?? null,
                $assignment->class_section_id ?? null,
                $assignment->session_id ?? null,
                $assignment->term_id ?? null,
            );

            if (! array_key_exists($key, $entries)) {
                $entries[$key] = [
                    'context_key' => $key,
                    'class' => $this->formatClass($assignment),
                    'class_arm' => $this->formatArm($assignment),
                    'class_section' => $this->formatSection($assignment),
                    'session' => $this->formatSession($assignment),
                    'term' => $this->formatTerm($assignment),
                    'subjects' => [],
                    'is_class_teacher' => false,
                ];
            }

            // Mark as class teacher if this is a class teacher assignment
            if ($isClassTeacher) {
                $entries[$key]['is_class_teacher'] = true;
            }

            if ($includeSubjects && $assignment->subject) {
                $entries[$key]['subjects'][$assignment->subject->id] = [
                    'id' => $assignment->subject->id,
                    'name' => $assignment->subject->name,
                    'code' => $assignment->subject->code,
                ];
            }
        };

        foreach ($this->subjectAssignments as $assignment) {
            $registerAssignment($assignment, true, false);
        }

        foreach ($this->classAssignments as $assignment) {
            $registerAssignment($assignment, false, true);
        }

        return collect($entries)
            ->map(function (array $entry) {
                // If this is a class teacher assignment, fetch all subjects for the class
                if ($entry['is_class_teacher'] && $entry['class']) {
                    $classId = $entry['class']['id'];
                    $classSubjects = \App\Models\SchoolClass::find($classId)?->subjects ?? collect();

                    foreach ($classSubjects as $subject) {
                        // Only add if not already present (subject teacher assignments take precedence)
                        if (! array_key_exists($subject->id, $entry['subjects'])) {
                            $entry['subjects'][$subject->id] = [
                                'id' => $subject->id,
                                'name' => $subject->name,
                                'code' => $subject->code,
                            ];
                        }
                    }
                }

                // Remove the is_class_teacher flag before returning
                unset($entry['is_class_teacher']);
                $entry['subjects'] = array_values($entry['subjects']);

                return $entry;
            })
            ->values();
    }

    /**
     * Get unique class IDs that the teacher is assigned to (either as class teacher or subject teacher).
     *
     * @return Collection<int, string>
     */
    public function allowedClassIds(): Collection
    {
        if (! $this->isTeacher) {
            return collect();
        }

        $classIds = collect();

        // Get class IDs from class teacher assignments
        $this->classAssignments->each(function ($assignment) use (&$classIds) {
            if ($assignment->school_class_id) {
                $classIds->push($assignment->school_class_id);
            }
        });

        // Get class IDs from subject teacher assignments
        $this->subjectAssignments->each(function ($assignment) use (&$classIds) {
            if ($assignment->school_class_id) {
                $classIds->push($assignment->school_class_id);
            }
        });

        return $classIds->unique()->values();
    }

    private function studentContexts(): Collection
    {
        $contexts = collect();

        $collectContext = static function ($assignment) use (&$contexts): void {
            if (! $assignment->school_class_id) {
                return;
            }

            $contexts->push([
                'school_class_id' => $assignment->school_class_id,
                'class_arm_id' => $assignment->class_arm_id ?? null,
                'class_section_id' => $assignment->class_section_id ?? null,
            ]);
        };

        $this->subjectAssignments->each($collectContext);
        $this->classAssignments->each($collectContext);

        return $contexts
            ->unique(fn (array $context) => implode(':', [
                $context['school_class_id'] ?? 'class-null',
                $context['class_arm_id'] ?? 'arm-null',
                $context['class_section_id'] ?? 'section-null',
            ]))
            ->values();
    }

    private function matchesContext(array $context, Student $student): bool
    {
        if ($context['school_class_id'] !== $student->school_class_id) {
            return false;
        }

        if ($context['class_arm_id'] && $context['class_arm_id'] !== $student->class_arm_id) {
            return false;
        }

        if ($context['class_section_id'] && $context['class_section_id'] !== $student->class_section_id) {
            return false;
        }

        return true;
    }

    private function contextKey(
        ?string $classId,
        ?string $armId,
        ?string $sectionId,
        ?string $sessionId,
        ?string $termId,
    ): string {
        return implode(':', [
            $classId ?? 'class-null',
            $armId ?? 'arm-null',
            $sectionId ?? 'section-null',
            $sessionId ?? 'session-null',
            $termId ?? 'term-null',
        ]);
    }

    private function formatClass($assignment): ?array
    {
        $class = $assignment->school_class;

        if (! $class) {
            return null;
        }

        return [
            'id' => $class->id,
            'name' => $class->name,
        ];
    }

    private function formatArm($assignment): ?array
    {
        $arm = $assignment->class_arm;

        if (! $arm) {
            return null;
        }

        return [
            'id' => $arm->id,
            'name' => $arm->name,
        ];
    }

    private function formatSection($assignment): ?array
    {
        $section = $assignment->class_section;

        if (! $section) {
            return null;
        }

        return [
            'id' => $section->id,
            'name' => $section->name,
        ];
    }

    private function formatSession($assignment): ?array
    {
        $session = $assignment->session;

        if (! $session) {
            return null;
        }

        return [
            'id' => $session->id,
            'name' => $session->name,
        ];
    }

    private function formatTerm($assignment): ?array
    {
        $term = $assignment->term;

        if (! $term) {
            return null;
        }

        return [
            'id' => $term->id,
            'name' => $term->name,
        ];
    }
}
