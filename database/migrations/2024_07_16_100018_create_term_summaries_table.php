<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('term_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('session_id');
            $table->uuid('term_id');
            $table->integer('total_marks_obtained');
            $table->integer('total_marks_possible');
            $table->decimal('average_score', 5, 2);
            $table->integer('position_in_class');
            $table->decimal('class_average_score', 5, 2);
            $table->integer('days_present')->nullable();
            $table->integer('days_absent')->nullable();
            $table->string('final_grade', 10)->nullable();
            $table->text('overall_comment')->nullable()->default('This student is good.');
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
            $table->foreign('term_id')->references('id')->on('terms')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('term_summaries');
    }
};
