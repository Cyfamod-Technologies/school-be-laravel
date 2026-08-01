<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('students')
            ->join('class_arms as assigned_arm', 'assigned_arm.id', '=', 'students.class_arm_id')
            ->whereNotNull('students.school_class_id')
            ->whereColumn('assigned_arm.school_class_id', '!=', 'students.school_class_id')
            ->select([
                'students.id',
                'students.school_class_id',
                'students.current_session_id',
                'students.class_arm_id',
                'assigned_arm.name as arm_name',
                'assigned_arm.slug as arm_slug',
            ])
            ->orderBy('students.id')
            ->chunkById(250, function ($students): void {
                foreach ($students as $student) {
                    $replacementArmId = DB::table('class_arms')
                        ->where('school_class_id', $student->school_class_id)
                        ->where(function ($query) use ($student) {
                            $query->where('slug', $student->arm_slug)
                                ->orWhereRaw('LOWER(name) = ?', [mb_strtolower((string) $student->arm_name)]);
                        })
                        ->value('id');

                    DB::table('students')->where('id', $student->id)->update([
                        'class_arm_id' => $replacementArmId,
                    ]);

                    DB::table('results')
                        ->where('student_id', $student->id)
                        ->where('session_id', $student->current_session_id)
                        ->where('school_class_id', $student->school_class_id)
                        ->where('class_arm_id', $student->class_arm_id)
                        ->update(['class_arm_id' => $replacementArmId]);

                    if (Schema::hasTable('promotion_logs')) {
                        DB::table('promotion_logs')
                            ->where('student_id', $student->id)
                            ->where('to_class_id', $student->school_class_id)
                            ->where('to_class_arm_id', $student->class_arm_id)
                            ->update(['to_class_arm_id' => $replacementArmId]);
                    }
                }
            }, 'students.id', 'id');
    }

    public function down(): void
    {
        // The previous invalid arm IDs cannot be restored safely.
    }
};
