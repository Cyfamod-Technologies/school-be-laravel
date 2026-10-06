<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Targets for a student-scoped fee assignment (excursions, uniforms,
 * damages -- anything billed to a hand-picked set of students).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structure_students', function (Blueprint $table) {
            $table->uuid('fee_structure_id');
            $table->uuid('student_id');
            $table->timestamps();

            $table->primary(['fee_structure_id', 'student_id']);
            $table->foreign('fee_structure_id')->references('id')->on('fee_structures')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structure_students');
    }
};
