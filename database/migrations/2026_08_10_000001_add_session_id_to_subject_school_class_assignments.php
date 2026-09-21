<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_school_class_assignments', function (Blueprint $table) {
            $table->uuid('session_id')->nullable()->after('subject_id');
            $table->foreign('session_id')
                ->references('id')
                ->on('sessions')
                ->nullOnDelete();
            $table->index(
                ['session_id', 'school_class_id', 'class_arm_id'],
                'subject_assignments_session_class_arm_index'
            );
        });

        DB::table('subject_school_class_assignments as assignments')
            ->join('subjects', 'subjects.id', '=', 'assignments.subject_id')
            ->join('schools', 'schools.id', '=', 'subjects.school_id')
            ->whereNull('assignments.session_id')
            ->whereNotNull('schools.current_session_id')
            ->update(['assignments.session_id' => DB::raw('schools.current_session_id')]);
    }

    public function down(): void
    {
        Schema::table('subject_school_class_assignments', function (Blueprint $table) {
            $table->dropIndex('subject_assignments_session_class_arm_index');
            $table->dropForeign(['session_id']);
            $table->dropColumn('session_id');
        });
    }
};
