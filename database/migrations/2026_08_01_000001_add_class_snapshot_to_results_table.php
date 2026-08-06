<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->uuid('school_class_id')->nullable()->after('session_id')->index();
            $table->uuid('class_arm_id')->nullable()->after('school_class_id')->index();
            $table->uuid('class_section_id')->nullable()->after('class_arm_id')->index();
        });

        DB::table('results')
            ->select(['id', 'student_id', 'session_id'])
            ->whereNull('school_class_id')
            ->orderBy('id')
            ->chunkById(500, function ($results): void {
                $studentIds = $results->pluck('student_id')->unique()->values();
                $sessionIds = $results->pluck('session_id')->unique()->values();

                $students = DB::table('students')
                    ->whereIn('id', $studentIds)
                    ->get(['id', 'school_class_id', 'class_arm_id', 'class_section_id'])
                    ->keyBy('id');

                $logs = Schema::hasTable('promotion_logs')
                    ? DB::table('promotion_logs')
                        ->whereIn('student_id', $studentIds)
                        ->where(function ($query) use ($sessionIds) {
                            $query->whereIn('from_session_id', $sessionIds)
                                ->orWhereIn('to_session_id', $sessionIds);
                        })
                        ->orderByDesc('promoted_at')
                        ->get()
                    : collect();

                foreach ($results as $result) {
                    $student = $students->get($result->student_id);
                    $fromLog = $logs->first(fn ($log) =>
                        $log->student_id === $result->student_id
                        && $log->from_session_id === $result->session_id
                    );
                    $toLog = $logs->first(fn ($log) =>
                        $log->student_id === $result->student_id
                        && $log->to_session_id === $result->session_id
                    );

                    $placement = $fromLog ?: $toLog;
                    $isSourcePlacement = $fromLog !== null;

                    DB::table('results')->where('id', $result->id)->update([
                        'school_class_id' => $placement
                            ? ($isSourcePlacement ? $placement->from_class_id : $placement->to_class_id)
                            : $student?->school_class_id,
                        'class_arm_id' => $placement
                            ? ($isSourcePlacement ? $placement->from_class_arm_id : $placement->to_class_arm_id)
                            : $student?->class_arm_id,
                        'class_section_id' => $placement
                            ? ($isSourcePlacement ? $placement->from_section_id : $placement->to_section_id)
                            : $student?->class_section_id,
                    ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn(['school_class_id', 'class_arm_id', 'class_section_id']);
        });
    }
};
