<?php

namespace App\Services\Fees;

use App\Jobs\SendPaymentStatusNotification;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The gate between "someone says they paid" and "the school has been paid".
 *
 * Every method here locks the payment row for the duration, so two admins
 * clicking Approve at the same moment cannot both credit the student.
 */
class PaymentVerificationService
{
    public function __construct(
        private readonly FinanceSequenceGenerator $sequences,
        private readonly PaymentAllocationService $allocations,
        private readonly FinanceAuditLogger $audit,
    ) {}

    /**
     * Confirm a payment: it becomes money, it gets a receipt number, and it is
     * spread across the student's unpaid fees.
     */
    public function approve(Payment $payment, User $actor): Payment
    {
        $payment = DB::transaction(function () use ($payment, $actor) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $this->assertPending($payment, 'approved');

            $before = $this->snapshot($payment);

            $payment->update([
                'status' => Payment::STATUS_VERIFIED,
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'receipt_number' => $payment->receipt_number
                    ?: $this->sequences->receiptNumber($payment->school),
                // A payment can be approved after an earlier rejection was
                // overturned; clear the stale rejection so the record does not
                // claim both.
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $this->audit->log(
                $payment->school_id,
                'payment.verified',
                'payment',
                $payment->id,
                $before,
                $this->snapshot($payment->fresh()),
                $actor,
            );

            return $payment->fresh();
        });

        // Allocation runs in its own transaction: a payment that is verified
        // but not yet explained is a recoverable state, and the student's
        // balance is already correct without it.
        $payment = $this->allocations->autoAllocate($payment, $actor);

        SendPaymentStatusNotification::dispatch($payment->id, Payment::STATUS_VERIFIED);

        return $payment;
    }

    /**
     * Turn a submission down. The record stays -- rejection is a decision with
     * a reason, not a delete.
     */
    public function reject(Payment $payment, string $reason, User $actor): Payment
    {
        $payment = DB::transaction(function () use ($payment, $reason, $actor) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $this->assertPending($payment, 'rejected');

            $before = $this->snapshot($payment);

            $payment->update([
                'status' => Payment::STATUS_REJECTED,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->audit->log(
                $payment->school_id,
                'payment.rejected',
                'payment',
                $payment->id,
                $before,
                $this->snapshot($payment->fresh()),
                $actor,
            );

            return $payment->fresh();
        });

        SendPaymentStatusNotification::dispatch($payment->id, Payment::STATUS_REJECTED);

        return $payment;
    }

    /**
     * Undo a verification that should not have happened.
     *
     * The allocations go with it -- money that was never really there cannot
     * be said to have paid for anything -- but the payment row survives, which
     * is the whole point: a reversal is part of the story, not a deletion of it.
     */
    public function reverse(Payment $payment, string $reason, User $actor): Payment
    {
        $payment = DB::transaction(function () use ($payment, $reason, $actor) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $payment->isVerified()) {
                throw ValidationException::withMessages([
                    'payment' => ['Only a verified payment can be reversed.'],
                ]);
            }

            $before = $this->snapshot($payment);

            $payment->allocations()->delete();

            $payment->update([
                'status' => Payment::STATUS_REVERSED,
                'reversed_by' => $actor->id,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ]);

            $this->audit->log(
                $payment->school_id,
                'payment.reversed',
                'payment',
                $payment->id,
                $before,
                $this->snapshot($payment->fresh()),
                $actor,
            );

            return $payment->fresh();
        });

        SendPaymentStatusNotification::dispatch($payment->id, Payment::STATUS_REVERSED);

        return $payment;
    }

    /**
     * Approving an already-verified payment must not credit the student twice,
     * so it is refused outright rather than treated as a no-op.
     */
    private function assertPending(Payment $payment, string $verb): void
    {
        if ($payment->isPending()) {
            return;
        }

        $current = str_replace('_', ' ', $payment->status);

        throw ValidationException::withMessages([
            'status' => ["This payment is already {$current} and cannot be {$verb} again."],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Payment $payment): array
    {
        return [
            'status' => $payment->status,
            'amount' => (string) $payment->amount,
            'receipt_number' => $payment->receipt_number,
            'verified_by' => $payment->verified_by,
            'verified_at' => $payment->verified_at?->toIso8601String(),
            'rejection_reason' => $payment->rejection_reason,
            'reversal_reason' => $payment->reversal_reason,
        ];
    }
}
