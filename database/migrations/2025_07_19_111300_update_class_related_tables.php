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
        Schema::table('class_arms', function (Blueprint $table) {
            $table->renameColumn('class_id', 'school_class_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('class_id', 'school_class_id');
        });

        Schema::rename('subject_class_assignments', 'subject_school_class_assignments');

        Schema::table('subject_school_class_assignments', function (Blueprint $table) {
            $table->renameColumn('class_id', 'school_class_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_arms', function (Blueprint $table) {
            $table->renameColumn('school_class_id', 'class_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('school_class_id', 'class_id');
        });

        Schema::rename('subject_school_class_assignments', 'subject_class_assignments');

        Schema::table('subject_class_assignments', function (Blueprint $table) {
            $table->renameColumn('school_class_id', 'class_id');
        });
    }
};
