<?php

namespace App\Services;

use App\Models\PromotionLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Session;
use App\Models\Term;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function promoteStudents(array $studentIds, array $payload, string $userId): array
    {
        if (empty($studentIds)) {
            throw ValidationException::withMessages([
                'student_ids' => 'Select at least one student for promotion.',
            ]);
        }

        return DB::transaction(function () use ($studentIds, $payload, $userId) {
            $results = [];
            foreach ($studentIds as $studentId) {
                $student = Student::query()->findOrFail($studentId);
                $results[] = $this->promoteStudent($student, $payload, $userId);
            }

            return $results;
        });
    }

    protected function promoteStudent(Student $student, array $payload, string $userId): array
    {
        $targetSession = Session::query()
            ->where('school_id', $student->school_id)
            ->findOrFail($payload['target_session_id']);

        $targetClass = SchoolClass::query()
            ->where('school_id', $student->school_id)
            ->findOrFail($payload['target_class_id']);

        $targetClassArmId = Arr::get($payload, 'target_class_arm_id');
        $targetSectionId = Arr::get($payload, 'target_section_id');

        // If a target class arm is explicitly provided, ensure it exists
        // for the target class. Otherwise, keep the student's existing arm.
        if ($targetClassArmId) {
            $student->class_arm()
                ->where('school_class_id', $targetClass->id)
                ->findOrFail($targetClassArmId);
        } else {
            $targetClassArmId = $student->class_arm_id;
        }

        $previousState = [
            'session_id' => $student->current_session_id,
            'term_id' => $student->current_term_id,
            'class_id' => $student->school_class_id,
            'class_arm_id' => $student->class_arm_id,
            'section_id' => $student->class_section_id,
        ];

        $targetTermId = $this->resolveFirstTermId($targetSession);

        if (! $targetTermId) {
            throw ValidationException::withMessages([
                'target_session_id' => 'The target session must have at least one term configured before promotion.',
            ]);
        }

        $student->current_session_id = $targetSession->id;
        $student->current_term_id = $targetTermId;
        $student->school_class_id = $targetClass->id;
        $student->class_arm_id = $targetClassArmId;
        $student->class_section_id = $targetSectionId ?: $student->class_section_id;
        $student->save();

        $log = PromotionLog::create([
            'student_id' => $student->id,
            'from_session_id' => $previousState['session_id'],
            'to_session_id' => $targetSession->id,
            'from_class_id' => $previousState['class_id'],
            'to_class_id' => $targetClass->id,
            'from_class_arm_id' => $previousState['class_arm_id'],
            'to_class_arm_id' => $targetClassArmId ?: null,
            'from_section_id' => $previousState['section_id'],
            'to_section_id' => $targetSectionId ?: null,
            'performed_by' => $userId,
            'meta' => [
                'retain_subjects' => (bool) Arr::get($payload, 'retain_subjects', false),
            ],
        ]);

        return [
            'student_id' => $student->id,
            'log_id' => $log->id,
        ];
    }

    protected function resolveFirstTermId(Session $session): ?string
    {
        return $session->terms()->orderBy('start_date')->value('id');
    }
}
