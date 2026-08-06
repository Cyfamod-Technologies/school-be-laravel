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
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->json('short_answer_answers')->nullable()->after('explanation');
            $table->json('short_answer_keywords')->nullable()->after('short_answer_answers');
            $table->string('short_answer_match')->default('exact')->after('short_answer_keywords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropColumn(['short_answer_answers', 'short_answer_keywords', 'short_answer_match']);
        });
    }
};
