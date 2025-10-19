<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->uuid('school_class_id')->nullable()->after('term_id');
            $table->uuid('class_arm_id')->nullable()->after('school_class_id');
            $table->uuid('class_section_id')->nullable()->after('class_arm_id');
            $table->uuid('recorded_by')->nullable()->after('status');
            $table->json('metadata')->nullable()->after('recorded_by');
        });

        // Remove duplicates prior to applying the unique constraint
        DB::table('attendances')
            ->select('student_id', 'date', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('student_id', 'date')
            ->having('aggregate', '>', 1)
            ->get()
            ->each(function ($duplicate) {
                DB::table('attendances')
                    ->where('student_id', $duplicate->student_id)
                    ->where('date', $duplicate->date)
                    ->orderBy('created_at')
                    ->skip(1)
                    ->take(PHP_INT_MAX)
                    ->delete();
            });

        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(['student_id', 'date'], 'attendances_student_date_unique');

            $table->foreign('school_class_id')
                ->references('id')
                ->on('classes')
                ->nullOnDelete();

            $table->foreign('class_arm_id')
                ->references('id')
                ->on('class_arms')
                ->nullOnDelete();

            $table->foreign('class_section_id')
                ->references('id')
                ->on('class_sections')
                ->nullOnDelete();

            $table->foreign('recorded_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['date', 'school_class_id'], 'attendances_date_class_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['school_class_id']);
            $table->dropForeign(['class_arm_id']);
            $table->dropForeign(['class_section_id']);
            $table->dropForeign(['recorded_by']);

            $table->dropIndex('attendances_date_class_index');
            $table->dropUnique('attendances_student_date_unique');

            $table->dropColumn([
                'school_class_id',
                'class_arm_id',
                'class_section_id',
                'recorded_by',
                'metadata',
            ]);
        });
    }
};
