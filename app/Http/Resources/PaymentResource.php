<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment as either side sees it.
 *
 * `include_review` carries the admin-only half: who verified or rejected it
 * and the evidence attached. A student sees the rejection reason (they need
 * to know why) but not the reviewer's identity.
 */
class PaymentResource extends JsonResource
{
    public function __construct($resource, private readonly bool $includeReview = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'reference' => $this->reference,
            'receipt_number' => $this->receipt_number,
            'payer_reference' => $this->payer_reference,
            'amount' => (string) $this->amount,
            'method' => $this->method,
            'paid_at' => optional($this->paid_at)->toDateString(),
            'note' => $this->note,
            'source' => $this->source,
            'status' => $this->status,
            'session_id' => $this->session_id,
            'term_id' => $this->term_id,
            'student_bill_id' => $this->student_bill_id,
            'created_at' => optional($this->created_at)->toIso8601String(),

            // A student has to be told why their evidence was turned down.
            'rejection_reason' => $this->rejection_reason,
            'reversal_reason' => $this->reversal_reason,
            'verified_at' => optional($this->verified_at)->toIso8601String(),

            'bank_detail' => $this->whenLoaded('bankDetail', fn () => $this->bankDetail ? [
                'id' => $this->bankDetail->id,
                'bank_name' => $this->bankDetail->bank_name,
                'account_name' => $this->bankDetail->account_name,
            ] : null),
            'session' => $this->whenLoaded('session', fn () => [
                'id' => $this->session->id,
                'name' => $this->session->name,
            ]),
            'term' => $this->whenLoaded('term', fn () => [
                'id' => $this->term->id,
                'name' => $this->term->name,
            ]),
            'evidence' => $this->whenLoaded('evidence', fn () => $this->evidence->map(fn ($file) => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size_bytes' => $file->size_bytes,
            ])->values()),

            // Which of the student's own fees this payment was applied to
            // (spec §2/§5's "Payment Details"). Not admin-only: it is their
            // own money, and this is a plainer answer to "what did my
            // ₦50,000 pay for" than the bill breakdown gives on its own.
            // Never includes who allocated it -- that stays admin-only below.
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'id' => $allocation->id,
                'student_bill_item_id' => $allocation->student_bill_item_id,
                'amount' => (string) $allocation->amount,
                'fee_name' => $allocation->relationLoaded('billItem') ? $allocation->billItem?->name : null,
            ])->values()),
            'unallocated_amount' => $this->when(
                $this->relationLoaded('allocations'),
                fn () => $this->unallocatedAmount(),
            ),
        ];

        if (! $this->includeReview) {
            return $payload;
        }

        return $payload + [
            // Required by the admin verification queue (spec §12): a reviewer
            // needs the student's class alongside their name to tell two
            // same-named students apart, or to spot a bill filed against the
            // wrong class.
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'admission_no' => $this->student->admission_no,
                'name' => trim($this->student->first_name.' '.$this->student->last_name),
                'class_name' => $this->student->relationLoaded('school_class')
                    ? $this->student->school_class?->name
                    : null,
                'class_arm_name' => $this->student->relationLoaded('class_arm')
                    ? $this->student->class_arm?->name
                    : null,
            ]),
            'submitted_by_type' => $this->submitted_by_type,
            'verified_by' => $this->whenLoaded('verifier', fn () => $this->verifier ? [
                'id' => $this->verifier->id,
                'name' => $this->verifier->name,
            ] : null),
            'rejected_by' => $this->whenLoaded('rejecter', fn () => $this->rejecter ? [
                'id' => $this->rejecter->id,
                'name' => $this->rejecter->name,
            ] : null),
            'rejected_at' => optional($this->rejected_at)->toIso8601String(),
            'reversed_at' => optional($this->reversed_at)->toIso8601String(),
        ];
    }
}
