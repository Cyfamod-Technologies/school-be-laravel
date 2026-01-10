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
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attempt_id');
            $table->uuid('question_id');
            $table->uuid('selected_option_id')->nullable();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->integer('marks_obtained')->default(0);
            $table->integer('time_spent_seconds')->nullable();
            $table->timestamp('answered_at');
            $table->timestamps();
            
            $table->foreign('attempt_id')->references('id')->on('quiz_attempts')->onDelete('cascade');
            $table->foreign('question_id')->references('id')->on('quiz_questions')->onDelete('cascade');
            $table->foreign('selected_option_id')->references('id')->on('quiz_options')->onDelete('set null');
            $table->index(['attempt_id', 'question_id']);
            $table->unique(['attempt_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }
};
