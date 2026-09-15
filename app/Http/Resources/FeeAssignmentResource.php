<?php

namespace App\Http\Resources;

use App\Models\FeeStructure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeeAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'scope' => $this->scope,
            // The label a bill shows for this fee's origin: "All Students",
            // "JSS 2", "JSS 2A" or the student count.
            'scope_label' => $this->scopeLabel(),
            'session_id' => $this->session_id,
            'term_id' => $this->term_id,
            'school_class_id' => $this->school_class_id,
            'class_arm_id' => $this->class_arm_id,
            'fee_item_id' => $this->fee_item_id,
            'amount' => (string) $this->amount,
            'description' => $this->description,
            'due_date' => optional($this->due_date)->toDateString(),
            'is_mandatory' => (bool) $this->is_mandatory,
            'is_active' => (bool) $this->is_active,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),

            'fee_item' => $this->whenLoaded('feeItem', fn () => [
                'id' => $this->feeItem->id,
                'name' => $this->feeItem->name,
                'category' => $this->feeItem->category,
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
            'students' => $this->whenLoaded('students', fn () => $this->students->map(fn ($student) => [
                'id' => $student->id,
                'admission_no' => $student->admission_no,
                'name' => trim($student->first_name.' '.$student->last_name),
            ])->values()),
            'student_count' => $this->when(
                isset($this->student_count),
                fn () => (int) $this->student_count,
            ),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
        ];
    }

    private function scopeLabel(): string
    {
        return match ($this->scope) {
            FeeStructure::SCOPE_SCHOOL => 'All Students',
            FeeStructure::SCOPE_CLASS => $this->relationLoaded('schoolClass') && $this->schoolClass
                ? $this->schoolClass->name
                : 'Class',
            FeeStructure::SCOPE_CLASS_ARM => $this->relationLoaded('classArm') && $this->classArm
                ? $this->classArm->name
                : 'Class Arm',
            FeeStructure::SCOPE_STUDENT => 'Selected Students',
            default => 'Unknown',
        };
    }
}
