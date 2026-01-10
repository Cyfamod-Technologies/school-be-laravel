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
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quiz_id');
            $table->text('question_text');
            $table->enum('question_type', ['mcq', 'multiple_select', 'true_false', 'short_answer']);
            $table->integer('marks');
            $table->integer('order');
            $table->string('image_url')->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();
            
            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
            $table->index(['quiz_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
