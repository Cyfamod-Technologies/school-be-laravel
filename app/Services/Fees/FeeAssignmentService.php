<?php

namespace App\Services\Fees;

use App\Models\ClassArm;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentBillItem;
use App\Models\Term;
use App\Models\User;
use App\Services\StudentSessionPlacementResolver;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating, changing and removing fee assignments, and answering the question
 * every other part of the module depends on: which students does this fee
 * actually apply to?
 */
class FeeAssignmentService
{
    public function __construct(
        private readonly StudentSessionPlacementResolver $placements,
        private readonly FinanceAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(School $school, array $data, ?User $actor = null): FeeStructure
    {
        $data = $this->validateScope($school, $data);

        return DB::transaction(function () use ($school, $data, $actor) {
            $assignment = new FeeStructure([
                'school_id' => $school->id,
                'scope' => $data['scope'],
                'school_class_id' => $data['school_class_id'] ?? null,
                'class_arm_id' => $data['class_arm_id'] ?? null,
                'session_id' => $data['session_id'],
                'term_id' => $data['term_id'],
                'fee_item_id' => $data['fee_item_id'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'is_mandatory' => $data['is_mandatory'] ?? true,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $actor?->id,
            ]);

            $this->guardAgainstDuplicate($assignment);

            $assignment->save();

            if ($assignment->scope === FeeStructure::SCOPE_STUDENT) {
                $assignment->students()->sync($data['student_ids']);
            }

            $this->audit->log(
                $school->id,
                FinanceAuditLogger::ACTION_ASSIGNMENT_CREATED,
                'fee_structure',
                $assignment->id,
                null,
                $this->snapshot($assignment),
                $actor,
            );

            return $assignment;
        });
    }

    /**
     * Scope, session, term and fee item are fixed at creation: changing any of
     * them makes it a different assignment, and the bills already generated
     * from it would silently mean something else. Amount and presentation are
     * editable.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(FeeStructure $assignment, array $data, ?User $actor = null): FeeStructure
    {
        return DB::transaction(function () use ($assignment, $data, $actor) {
            $before = $this->snapshot($assignment);

            // array_key_exists, not isset: clearing a due date or a
            // description back to null is a legitimate edit.
            foreach (['amount', 'description', 'due_date'] as $field) {
                if (array_key_exists($field, $data)) {
                    $assignment->{$field} = $data[$field];
                }
            }

            if (array_key_exists('is_mandatory', $data)) {
                $assignment->is_mandatory = (bool) $data['is_mandatory'];
            }

            if (array_key_exists('is_active', $data)) {
                $assignment->is_active = (bool) $data['is_active'];
            }

            $assignment->save();

            if ($assignment->scope === FeeStructure::SCOPE_STUDENT && array_key_exists('student_ids', $data)) {
                $assignment->students()->sync(
                    $this->validateStudentIds($assignment->school_id, (array) $data['student_ids'])
                );
            }

            $this->audit->log(
                $assignment->school_id,
                FinanceAuditLogger::ACTION_ASSIGNMENT_UPDATED,
                'fee_structure',
                $assignment->id,
                $before,
                $this->snapshot($assignment->fresh()),
                $actor,
            );

            return $assignment;
        });
    }

    /**
     * Remove an assignment and take its fee off every bill it raised.
     *
     * The bill items have to be dealt with here, before the row goes. The
     * foreign key is nullOnDelete, so the moment the assignment disappears
     * every line it created is left with a null fee_structure_id -- invisible
     * to the generation sync, and still counting towards what the student
     * owes. Lines that money was allocated to are retired rather than deleted,
     * so the allocation keeps its meaning.
     *
     * @return int the number of bill lines taken off
     */
    public function delete(FeeStructure $assignment, ?User $actor = null): int
    {
        return DB::transaction(function () use ($assignment, $actor) {
            $before = $this->snapshot($assignment);
            $schoolId = $assignment->school_id;
            $assignmentId = $assignment->id;

            $removed = 0;

            StudentBillItem::query()
                ->where('fee_structure_id', $assignmentId)
                ->with('allocations')
                ->each(function (StudentBillItem $item) use (&$removed) {
                    $item->retireOrDelete('The fee assignment behind this line was removed.');
                    $removed++;
                });

            $assignment->delete();

            $this->audit->log(
                $schoolId,
                FinanceAuditLogger::ACTION_ASSIGNMENT_DELETED,
                'fee_structure',
                $assignmentId,
                $before,
                ['bill_items_removed' => $removed],
                $actor,
            );

            return $removed;
        });
    }

    /**
     * The students an assignment bills, without writing anything.
     *
     * @return array<int, string>
     */
    public function resolveStudentIds(FeeStructure $assignment): array
    {
        if ($assignment->scope === FeeStructure::SCOPE_STUDENT) {
            return $assignment->students()->pluck('students.id')->map(fn ($id) => (string) $id)->all();
        }

        $students = $this->billableStudents($assignment->school_id);

        if ($assignment->scope === FeeStructure::SCOPE_SCHOOL) {
            return $students->pluck('id')->map(fn ($id) => (string) $id)->all();
        }

        $placements = $this->placements->resolveMany($students, $assignment->session_id);

        $key = $assignment->scope === FeeStructure::SCOPE_CLASS ? 'school_class_id' : 'class_arm_id';
        $target = $assignment->scope === FeeStructure::SCOPE_CLASS
            ? $assignment->school_class_id
            : $assignment->class_arm_id;

        return $students
            ->filter(fn (Student $student) => (string) ($placements[(string) $student->id][$key] ?? '') === (string) $target)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * What an assignment would cost before it is committed -- how many
     * students it reaches and what that adds up to.
     *
     * @param  array<string, mixed>  $data
     * @return array{student_count: int, amount: string, total_amount: string}
     */
    public function preview(School $school, array $data): array
    {
        $data = $this->validateScope($school, $data);

        $draft = new FeeStructure([
            'school_id' => $school->id,
            'scope' => $data['scope'],
            'school_class_id' => $data['school_class_id'] ?? null,
            'class_arm_id' => $data['class_arm_id'] ?? null,
            'session_id' => $data['session_id'],
            'term_id' => $data['term_id'],
            'fee_item_id' => $data['fee_item_id'],
            'amount' => $data['amount'],
        ]);

        $studentIds = $draft->scope === FeeStructure::SCOPE_STUDENT
            ? $data['student_ids']
            : $this->resolveStudentIds($draft);

        $count = count($studentIds);
        $amount = Money::of($data['amount']);

        return [
            'student_count' => $count,
            'amount' => $amount,
            'total_amount' => Money::multiplyByInt($amount, $count),
        ];
    }

    /**
     * Students a school can bill. Alumni and withdrawn students keep their
     * historic bills but stop attracting new ones.
     *
     * @return \Illuminate\Support\Collection<int, Student>
     */
    public function billableStudents(string $schoolId, ?array $studentIds = null)
    {
        return Student::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->when($studentIds !== null, fn ($query) => $query->whereIn('id', $studentIds))
            ->get([
                'id',
                'school_id',
                'current_session_id',
                'school_class_id',
                'class_arm_id',
                'class_section_id',
            ]);
    }

    /**
     * Assignments that apply to one student, given where they sat that session.
     *
     * @return \Illuminate\Support\Collection<int, FeeStructure>
     */
    public function assignmentsForStudent(
        string $schoolId,
        string $studentId,
        string $sessionId,
        string $termId,
        ?string $schoolClassId,
        ?string $classArmId,
    ) {
        return FeeStructure::query()
            ->where('school_id', $schoolId)
            ->where('session_id', $sessionId)
            ->where('term_id', $termId)
            ->where('is_active', true)
            ->where(function ($query) use ($studentId, $schoolClassId, $classArmId) {
                $query->where('scope', FeeStructure::SCOPE_SCHOOL);

                if ($schoolClassId) {
                    $query->orWhere(fn ($q) => $q
                        ->where('scope', FeeStructure::SCOPE_CLASS)
                        ->where('school_class_id', $schoolClassId));
                }

                if ($classArmId) {
                    $query->orWhere(fn ($q) => $q
                        ->where('scope', FeeStructure::SCOPE_CLASS_ARM)
                        ->where('class_arm_id', $classArmId));
                }

                $query->orWhere(fn ($q) => $q
                    ->where('scope', FeeStructure::SCOPE_STUDENT)
                    ->whereHas('students', fn ($s) => $s->where('students.id', $studentId)));
            })
            ->with('feeItem')
            ->get();
    }

    /**
     * Rejects a duplicate before the unique index does, so the caller gets a
     * validation error rather than a 500.
     */
    private function guardAgainstDuplicate(FeeStructure $assignment): void
    {
        $key = FeeStructure::makeAssignmentKey(
            $assignment->school_id,
            $assignment->scope,
            $assignment->school_class_id,
            $assignment->class_arm_id,
            $assignment->session_id,
            $assignment->term_id,
            $assignment->fee_item_id,
        );

        // Student-scoped assignments have no natural key -- two excursions in
        // one term are a real thing -- so there is nothing to clash with.
        if ($key === null) {
            return;
        }

        $exists = FeeStructure::query()
            ->where('assignment_key', $key)
            ->when($assignment->exists, fn ($query) => $query->whereKeyNot($assignment->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'fee_item_id' => ['This fee is already assigned to that scope for the selected session and term.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateScope(School $school, array $data): array
    {
        $scope = $data['scope'] ?? FeeStructure::SCOPE_CLASS;

        if (! in_array($scope, FeeStructure::SCOPES, true)) {
            throw ValidationException::withMessages([
                'scope' => ['Select a valid scope: '.implode(', ', FeeStructure::SCOPES).'.'],
            ]);
        }

        $data['scope'] = $scope;

        $this->assertBelongsToSchool(Session::class, $data['session_id'] ?? null, $school->id, 'session_id');
        $this->assertBelongsToSchool(Term::class, $data['term_id'] ?? null, $school->id, 'term_id');
        $this->assertBelongsToSchool(FeeItem::class, $data['fee_item_id'] ?? null, $school->id, 'fee_item_id');

        // Each scope needs exactly its own target, and nothing else. Carrying a
        // stale class_arm_id into a school-wide fee would poison the
        // assignment key and let a duplicate through.
        return match ($scope) {
            FeeStructure::SCOPE_SCHOOL => $this->clearTargets($data),
            FeeStructure::SCOPE_CLASS => $this->requireClass($school, $data),
            FeeStructure::SCOPE_CLASS_ARM => $this->requireClassArm($school, $data),
            FeeStructure::SCOPE_STUDENT => $this->requireStudents($school, $data),
        };
    }

    private function clearTargets(array $data): array
    {
        $data['school_class_id'] = null;
        $data['class_arm_id'] = null;
        $data['student_ids'] = [];

        return $data;
    }

    private function requireClass(School $school, array $data): array
    {
        $this->assertBelongsToSchool(SchoolClass::class, $data['school_class_id'] ?? null, $school->id, 'school_class_id');

        $data['class_arm_id'] = null;
        $data['student_ids'] = [];

        return $data;
    }

    private function requireClassArm(School $school, array $data): array
    {
        $armId = $data['class_arm_id'] ?? null;

        $arm = ClassArm::query()
            ->whereKey($armId)
            ->whereHas('school_class', fn ($query) => $query->where('school_id', $school->id))
            ->first();

        if (! $arm) {
            throw ValidationException::withMessages([
                'class_arm_id' => ['Select a class arm that belongs to this school.'],
            ]);
        }

        // Keep the parent class on the row so arm fees can still be filtered
        // and reported by class without a join.
        $data['school_class_id'] = $arm->school_class_id;
        $data['student_ids'] = [];

        return $data;
    }

    private function requireStudents(School $school, array $data): array
    {
        $data['school_class_id'] = null;
        $data['class_arm_id'] = null;
        $data['student_ids'] = $this->validateStudentIds($school->id, (array) ($data['student_ids'] ?? []));

        return $data;
    }

    /**
     * @param  array<int, string>  $studentIds
     * @return array<int, string>
     */
    private function validateStudentIds(string $schoolId, array $studentIds): array
    {
        $studentIds = array_values(array_unique(array_filter($studentIds)));

        if ($studentIds === []) {
            throw ValidationException::withMessages([
                'student_ids' => ['Select at least one student for a student-specific fee.'],
            ]);
        }

        $found = Student::query()
            ->where('school_id', $schoolId)
            ->whereIn('id', $studentIds)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (count($found) !== count($studentIds)) {
            throw ValidationException::withMessages([
                'student_ids' => ['One or more selected students do not belong to this school.'],
            ]);
        }

        return $found;
    }

    private function assertBelongsToSchool(string $model, ?string $id, string $schoolId, string $field): void
    {
        $exists = $id && $model::query()
            ->whereKey($id)
            ->where('school_id', $schoolId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                $field => ['Select a value that belongs to this school.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(FeeStructure $assignment): array
    {
        return [
            'scope' => $assignment->scope,
            'school_class_id' => $assignment->school_class_id,
            'class_arm_id' => $assignment->class_arm_id,
            'session_id' => $assignment->session_id,
            'term_id' => $assignment->term_id,
            'fee_item_id' => $assignment->fee_item_id,
            'amount' => (string) $assignment->amount,
            'is_mandatory' => (bool) $assignment->is_mandatory,
            'is_active' => (bool) $assignment->is_active,
            'due_date' => $assignment->due_date?->toDateString(),
        ];
    }
}
