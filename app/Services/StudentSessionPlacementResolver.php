<?php

namespace App\Services;

use App\Models\PromotionLog;
use App\Models\Student;

class StudentSessionPlacementResolver
{
    public function resolve(Student $student, ?string $sessionId): array
    {
        if (! $sessionId || (string) $student->current_session_id === $sessionId) {
            return $this->currentPlacement($student);
        }

        $logs = PromotionLog::query()
            ->where('student_id', $student->id)
            ->where(function ($query) use ($sessionId) {
                $query->where('from_session_id', $sessionId)
                    ->orWhere('to_session_id', $sessionId);
            })
            ->orderByDesc('promoted_at')
            ->get();

        $sourceLog = $logs->first(
            fn (PromotionLog $log) => (string) $log->from_session_id === $sessionId
        );

        if ($sourceLog) {
            return [
                'school_class_id' => $sourceLog->from_class_id,
                'class_arm_id' => $sourceLog->from_class_arm_id,
                'class_section_id' => $sourceLog->from_section_id,
            ];
        }

        $targetLog = $logs->first(
            fn (PromotionLog $log) => (string) $log->to_session_id === $sessionId
        );

        if ($targetLog) {
            return [
                'school_class_id' => $targetLog->to_class_id,
                'class_arm_id' => $targetLog->to_class_arm_id,
                'class_section_id' => $targetLog->to_section_id,
            ];
        }

        return $this->currentPlacement($student);
    }

    private function currentPlacement(Student $student): array
    {
        return [
            'school_class_id' => $student->school_class_id,
            'class_arm_id' => $student->class_arm_id,
            'class_section_id' => $student->class_section_id,
        ];
    }
}
