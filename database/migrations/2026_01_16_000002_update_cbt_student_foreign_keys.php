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
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });

        Schema::table('quiz_results', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('quiz_results', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
