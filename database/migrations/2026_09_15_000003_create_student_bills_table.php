<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One bill per student per session/term.
 *
 * Materialised rather than derived because a bill has to remember the class
 * the student was in when it was raised -- a mid-term transfer or an end-of-
 * year promotion would otherwise silently rewrite last term's history.
 *
 * Deliberately carries no cached totals: every figure is aggregated from
 * bill items, adjustments and payments at read time (see BillCalculator) so
 * the numbers cannot drift out of step with the records behind them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('student_id');
            $table->uuid('session_id');
            $table->uuid('term_id');

            // Snapshot of where the student sat when the bill was generated.
            $table->uuid('school_class_id')->nullable();
            $table->uuid('class_arm_id')->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            $table->foreign('school_class_id')->references('id')->on('classes')->nullOnDelete();
            $table->foreign('class_arm_id')->references('id')->on('class_arms')->nullOnDelete();

            $table->unique(['student_id', 'session_id', 'term_id'], 'student_bills_student_period_unique');
            $table->index(['school_id', 'session_id', 'term_id'], 'student_bills_school_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_bills');
    }
};
