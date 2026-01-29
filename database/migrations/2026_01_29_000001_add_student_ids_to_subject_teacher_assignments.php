<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_teacher_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('subject_teacher_assignments', 'student_ids')) {
                $table->json('student_ids')->nullable()->after('class_section_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subject_teacher_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('subject_teacher_assignments', 'student_ids')) {
                $table->dropColumn('student_ids');
            }
        });
    }
};
