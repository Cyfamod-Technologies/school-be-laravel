<?php

namespace App\Services;

use App\Models\PromotionLog;
use App\Models\Student;
use Illuminate\Support\Collection;

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

        return $this->placementFromLogs($logs, $sessionId) ?? $this->currentPlacement($student);
    }

    /**
     * The same resolution for many students at once.
     *
     * Bill generation asks this for every student in a school, so the
     * per-student promotion-log lookup in resolve() would be thousands of
     * queries. This collapses it to one.
     *
     * @param  iterable<Student>  $students
     * @return array<string, array{school_class_id: ?string, class_arm_id: ?string, class_section_id: ?string}>
     */
    public function resolveMany(iterable $students, ?string $sessionId): array
    {
        $placements = [];
        $needsLookup = [];

        foreach ($students as $student) {
            // Fall back to the current placement up front, so a student with
            // no promotion history for that session still gets an answer.
            $placements[(string) $student->id] = $this->currentPlacement($student);

            if ($sessionId && (string) $student->current_session_id !== $sessionId) {
                $needsLookup[] = (string) $student->id;
            }
        }

        if ($needsLookup === []) {
            return $placements;
        }

        $logsByStudent = PromotionLog::query()
            ->whereIn('student_id', $needsLookup)
            ->where(function ($query) use ($sessionId) {
                $query->where('from_session_id', $sessionId)
                    ->orWhere('to_session_id', $sessionId);
            })
            ->orderByDesc('promoted_at')
            ->get()
            ->groupBy('student_id');

        foreach ($logsByStudent as $studentId => $logs) {
            $placement = $this->placementFromLogs($logs, $sessionId);

            if ($placement) {
                $placements[(string) $studentId] = $placement;
            }
        }

        return $placements;
    }

    /**
     * A log whose `from` side is the session we want tells us where the
     * student sat during it, which beats a log that only says where they
     * landed at the end of it.
     *
     * @param  Collection<int, PromotionLog>  $logs
     */
    private function placementFromLogs(Collection $logs, string $sessionId): ?array
    {
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

        return null;
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
