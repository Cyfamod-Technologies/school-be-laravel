<?php

namespace App\Services\Fees;

use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\StudentBillItem;
use App\Models\User;
use App\Services\StudentSessionPlacementResolver;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns fee assignments into the lines a student actually sees on their bill.
 *
 * Generation is idempotent: running it twice produces one set of lines, and
 * running it after an amount changes updates them in place. That is what makes
 * it safe to call from an assignment save, from a student transfer, and from
 * the artisan command, without anyone having to reason about ordering.
 *
 * The one thing it will not do is destroy history. A line whose assignment has
 * been withdrawn is deleted only while no payment has been allocated to it;
 * once money has touched it, it is retired with a reason instead.
 */
class BillGenerationService
{
    public function __construct(
        private readonly FeeAssignmentService $assignments,
        private readonly StudentSessionPlacementResolver $placements,
        private readonly FinanceAuditLogger $audit,
    ) {}

    /**
     * Rebuild the bills for a school's students in one session/term.
     *
     * @param  array<int, string>|null  $studentIds  null means every billable student
     * @return array{students: int, items_created: int, items_updated: int, items_removed: int}
     */
    public function generateForSchool(
        string $schoolId,
        string $sessionId,
        string $termId,
        ?array $studentIds = null,
        ?User $actor = null,
    ): array {
        $students = $this->assignments->billableStudents($schoolId, $studentIds);

        if ($students->isEmpty()) {
            return $this->emptySummary();
        }

        $placements = $this->placements->resolveMany($students, $sessionId);
        $assignments = $this->assignmentsForPeriod($schoolId, $sessionId, $termId);

        $summary = $this->emptySummary();

        foreach ($students as $student) {
            $placement = $placements[(string) $student->id] ?? [
                'school_class_id' => null,
                'class_arm_id' => null,
            ];

            $result = $this->syncStudent(
                $student,
                $sessionId,
                $termId,
                $placement,
                $assignments,
            );

            $summary['students']++;
            $summary['items_created'] += $result['items_created'];
            $summary['items_updated'] += $result['items_updated'];
            $summary['items_removed'] += $result['items_removed'];
        }

        $this->audit->log(
            $schoolId,
            FinanceAuditLogger::ACTION_BILL_GENERATED,
            'student_bill',
            null,
            null,
            $summary + ['session_id' => $sessionId, 'term_id' => $termId],
            $actor,
        );

        return $summary;
    }

    /**
     * Rebuild the bills of every student an assignment reaches. Called after an
     * assignment is created or changed.
     *
     * @return array{students: int, items_created: int, items_updated: int, items_removed: int}
     */
    public function syncAssignment(FeeStructure $assignment, ?User $actor = null): array
    {
        return $this->generateForSchool(
            $assignment->school_id,
            $assignment->session_id,
            $assignment->term_id,
            $this->assignments->resolveStudentIds($assignment),
            $actor,
        );
    }

    /**
     * Rebuild one student's bill.
     *
     * @return array{items_created: int, items_updated: int, items_removed: int}
     */
    public function generateForStudent(Student $student, string $sessionId, string $termId): array
    {
        $placement = $this->placements->resolve($student, $sessionId);

        return $this->syncStudent(
            $student,
            $sessionId,
            $termId,
            $placement,
            $this->assignmentsForPeriod($student->school_id, $sessionId, $termId),
        );
    }

    /**
     * @param  array{school_class_id: ?string, class_arm_id: ?string}  $placement
     * @param  Collection<int, FeeStructure>  $assignments  every active assignment for the period
     * @return array{items_created: int, items_updated: int, items_removed: int}
     */
    private function syncStudent(
        Student $student,
        string $sessionId,
        string $termId,
        array $placement,
        Collection $assignments,
    ): array {
        $applicable = $this->applicableTo($assignments, $student->id, $placement);

        return DB::transaction(function () use ($student, $sessionId, $termId, $placement, $applicable) {
            $bill = StudentBill::query()->firstOrNew([
                'student_id' => $student->id,
                'session_id' => $sessionId,
                'term_id' => $termId,
            ]);

            $bill->school_id = $student->school_id;
            // Re-stamped on every sync: a sync is a deliberate act that
            // re-bases the bill on where the student sits now. Closed terms
            // are never synced, so past bills keep the placement they had.
            $bill->school_class_id = $placement['school_class_id'] ?? null;
            $bill->class_arm_id = $placement['class_arm_id'] ?? null;
            $bill->generated_at = now();
            $bill->save();

            $existing = $bill->items()
                ->whereNotNull('fee_structure_id')
                ->get()
                ->keyBy('fee_structure_id');

            $created = 0;
            $updated = 0;

            foreach ($applicable as $assignment) {
                $item = $existing->get($assignment->id);

                if ($item) {
                    $updated += $this->refreshItem($item, $assignment) ? 1 : 0;

                    continue;
                }

                $this->createItem($bill, $assignment);
                $created++;
            }

            $removed = $this->retireStaleItems(
                $existing,
                $applicable->pluck('id')->map(fn ($id) => (string) $id)->all(),
            );

            return [
                'items_created' => $created,
                'items_updated' => $updated,
                'items_removed' => $removed,
            ];
        });
    }

    private function createItem(StudentBill $bill, FeeStructure $assignment): StudentBillItem
    {
        $amount = Money::of($assignment->amount);

        return StudentBillItem::create([
            'school_id' => $bill->school_id,
            'student_bill_id' => $bill->id,
            'fee_structure_id' => $assignment->id,
            'fee_item_id' => $assignment->fee_item_id,
            // Snapshotted: renaming the fee item later must not rewrite what
            // an already-settled bill says it was for.
            'name' => $assignment->feeItem?->name ?? 'Fee',
            'description' => $assignment->description,
            'source' => $assignment->scope,
            'amount' => $amount,
            'discount_amount' => '0.00',
            'surcharge_amount' => '0.00',
            'net_amount' => $amount,
            'is_removed' => false,
        ]);
    }

    /**
     * Brings a line back in step with its assignment, preserving any
     * adjustments already applied to it.
     */
    private function refreshItem(StudentBillItem $item, FeeStructure $assignment): bool
    {
        $amount = Money::of($assignment->amount);
        $net = Money::atLeastZero(
            Money::add(Money::sub($amount, $item->discount_amount), $item->surcharge_amount)
        );

        $changes = [];

        if (Money::compare($item->amount, $amount) !== 0) {
            $changes['amount'] = $amount;
            $changes['net_amount'] = $net;
        }

        if ($item->source !== $assignment->scope) {
            $changes['source'] = $assignment->scope;
        }

        // A line withdrawn earlier and re-assigned since is live again.
        if ($item->is_removed) {
            $changes['is_removed'] = false;
            $changes['removed_reason'] = null;
        }

        if ($changes === []) {
            return false;
        }

        $item->fill($changes)->save();

        return true;
    }

    /**
     * Lines whose assignment no longer applies -- the fee was deactivated, or
     * the student moved to a class it does not cover.
     *
     * @param  Collection<string, StudentBillItem>  $existing
     * @param  array<int, string>  $applicableIds
     */
    private function retireStaleItems(Collection $existing, array $applicableIds): int
    {
        $stale = $existing->reject(
            fn (StudentBillItem $item) => in_array((string) $item->fee_structure_id, $applicableIds, true)
        );

        $removed = 0;

        foreach ($stale as $item) {
            if ($item->is_removed) {
                continue;
            }

            // Money has been pinned to this line. Deleting it would orphan the
            // allocation and silently change what the student paid for.
            if ($item->allocations()->exists()) {
                $item->update([
                    'is_removed' => true,
                    'removed_reason' => 'Fee no longer applies to this student.',
                ]);
            } else {
                $item->delete();
            }

            $removed++;
        }

        return $removed;
    }

    /**
     * @param  Collection<int, FeeStructure>  $assignments
     * @param  array{school_class_id: ?string, class_arm_id: ?string}  $placement
     * @return Collection<int, FeeStructure>
     */
    private function applicableTo(Collection $assignments, string $studentId, array $placement): Collection
    {
        return $assignments->filter(function (FeeStructure $assignment) use ($studentId, $placement) {
            return match ($assignment->scope) {
                FeeStructure::SCOPE_SCHOOL => true,
                FeeStructure::SCOPE_CLASS => $placement['school_class_id']
                    && (string) $assignment->school_class_id === (string) $placement['school_class_id'],
                FeeStructure::SCOPE_CLASS_ARM => $placement['class_arm_id']
                    && (string) $assignment->class_arm_id === (string) $placement['class_arm_id'],
                FeeStructure::SCOPE_STUDENT => $assignment->students
                    ->contains(fn ($student) => (string) $student->id === (string) $studentId),
                default => false,
            };
        })->values();
    }

    /**
     * Every active assignment for the period, loaded once so a school-wide
     * generation run does not re-query per student.
     *
     * @return Collection<int, FeeStructure>
     */
    private function assignmentsForPeriod(string $schoolId, string $sessionId, string $termId): Collection
    {
        return FeeStructure::query()
            ->where('school_id', $schoolId)
            ->where('session_id', $sessionId)
            ->where('term_id', $termId)
            ->where('is_active', true)
            ->with(['feeItem:id,name', 'students:id'])
            ->get();
    }

    /**
     * @return array{students: int, items_created: int, items_updated: int, items_removed: int}
     */
    private function emptySummary(): array
    {
        return [
            'students' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'items_removed' => 0,
        ];
    }
}
