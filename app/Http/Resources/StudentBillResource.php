<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student's bill for one session/term.
 *
 * `totals` and `paid_per_item` are never computed here -- they come from
 * BillCalculator and are injected by the controller, so the portal and the
 * dashboard read exactly the same numbers.
 */
class StudentBillResource extends JsonResource
{
    /**
     * @param  array<string, string>|null  $totals
     * @param  array<string, string>  $paidPerItem
     */
    public function __construct(
        $resource,
        private readonly ?array $totals = null,
        private readonly array $paidPerItem = [],
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'student_id' => $this->student_id,
            'session_id' => $this->session_id,
            'term_id' => $this->term_id,
            'school_class_id' => $this->school_class_id,
            'class_arm_id' => $this->class_arm_id,
            'generated_at' => optional($this->generated_at)->toIso8601String(),

            'totals' => $this->totals,

            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'admission_no' => $this->student->admission_no,
                'name' => trim($this->student->first_name.' '.$this->student->last_name),
            ]),
            'school_class' => $this->whenLoaded('schoolClass', fn () => $this->schoolClass ? [
                'id' => $this->schoolClass->id,
                'name' => $this->schoolClass->name,
            ] : null),
            'class_arm' => $this->whenLoaded('classArm', fn () => $this->classArm ? [
                'id' => $this->classArm->id,
                'name' => $this->classArm->name,
            ] : null),
            'session' => $this->whenLoaded('session', fn () => [
                'id' => $this->session->id,
                'name' => $this->session->name,
            ]),
            'term' => $this->whenLoaded('term', fn () => [
                'id' => $this->term->id,
                'name' => $this->term->name,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'fee_structure_id' => $item->fee_structure_id,
                'fee_item_id' => $item->fee_item_id,
                'name' => $item->name,
                'description' => $item->description,
                'source' => $item->source,
                'amount' => (string) $item->amount,
                'discount_amount' => (string) $item->discount_amount,
                'surcharge_amount' => (string) $item->surcharge_amount,
                'net_amount' => (string) $item->net_amount,
                'paid_amount' => $this->paidPerItem[$item->id] ?? '0.00',
                'is_removed' => (bool) $item->is_removed,
                'removed_reason' => $item->removed_reason,
            ])->values()),
        ];
    }
}
