<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('class_arms', 'class_id') && ! Schema::hasColumn('class_arms', 'school_class_id')) {
            Schema::table('class_arms', function (Blueprint $table) {
                $table->renameColumn('class_id', 'school_class_id');
            });
        }

        if (Schema::hasColumn('students', 'class_id') && ! Schema::hasColumn('students', 'school_class_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->renameColumn('class_id', 'school_class_id');
            });
        }

        if (Schema::hasTable('subject_class_assignments') && ! Schema::hasTable('subject_school_class_assignments')) {
            Schema::rename('subject_class_assignments', 'subject_school_class_assignments');
        }

        if (Schema::hasTable('subject_school_class_assignments') && Schema::hasColumn('subject_school_class_assignments', 'class_id')) {
            Schema::table('subject_school_class_assignments', function (Blueprint $table) {
                $table->renameColumn('class_id', 'school_class_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('class_arms', 'school_class_id') && ! Schema::hasColumn('class_arms', 'class_id')) {
            Schema::table('class_arms', function (Blueprint $table) {
                $table->renameColumn('school_class_id', 'class_id');
            });
        }

        if (Schema::hasColumn('students', 'school_class_id') && ! Schema::hasColumn('students', 'class_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->renameColumn('school_class_id', 'class_id');
            });
        }

        if (Schema::hasTable('subject_school_class_assignments') && ! Schema::hasTable('subject_class_assignments')) {
            Schema::rename('subject_school_class_assignments', 'subject_class_assignments');
        }

        if (Schema::hasTable('subject_class_assignments') && Schema::hasColumn('subject_class_assignments', 'school_class_id')) {
            Schema::table('subject_class_assignments', function (Blueprint $table) {
                $table->renameColumn('school_class_id', 'class_id');
            });
        }
    }
};
