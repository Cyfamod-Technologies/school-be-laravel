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
        Schema::create('quiz_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attempt_id')->unique();
            $table->uuid('quiz_id');
            $table->uuid('student_id');
            $table->integer('total_questions');
            $table->integer('attempted_questions');
            $table->integer('correct_answers');
            $table->integer('total_marks');
            $table->integer('marks_obtained');
            $table->decimal('percentage', 5, 2);
            $table->char('grade', 1);
            $table->enum('status', ['pass', 'fail']);
            $table->timestamp('submitted_at');
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            
            $table->foreign('attempt_id')->references('id')->on('quiz_attempts')->onDelete('cascade');
            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['student_id', 'quiz_id']);
            $table->index(['status']);
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_results');
    }
};
