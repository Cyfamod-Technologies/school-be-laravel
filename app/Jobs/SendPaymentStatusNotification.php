<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Notifications\StudentPushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a student/parent what happened to a payment they submitted.
 */
class SendPaymentStatusNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $paymentId,
        public readonly string $status,
    ) {}

    public function handle(StudentPushNotificationService $notifications): void
    {
        $payment = Payment::query()
            ->with(['student.devices'])
            ->find($this->paymentId);

        // A payment that moved on again since this job was queued should not
        // send a message that is already out of date.
        if (! $payment || $payment->status !== $this->status || ! $payment->student) {
            return;
        }

        $amount = number_format((float) $payment->amount, 2);

        [$title, $body] = match ($this->status) {
            Payment::STATUS_VERIFIED => [
                'Payment Confirmed',
                "Your payment of NGN {$amount} has been verified. Reference {$payment->reference}.",
            ],
            Payment::STATUS_REJECTED => [
                'Payment Not Accepted',
                trim("Your payment of NGN {$amount} could not be verified. ".(string) $payment->rejection_reason),
            ],
            Payment::STATUS_REVERSED => [
                'Payment Reversed',
                trim("A verified payment of NGN {$amount} has been reversed. ".(string) $payment->reversal_reason),
            ],
            default => [null, null],
        };

        if (! $title) {
            return;
        }

        $notifications->deliver(
            $payment->student,
            'fees',
            $title,
            $body,
            [
                'type' => 'fees',
                'payment_id' => (string) $payment->id,
                'reference' => (string) $payment->reference,
                'status' => (string) $payment->status,
                'route' => '/fees',
            ],
            "payment:{$payment->id}:status:{$this->status}",
        );
    }
}
