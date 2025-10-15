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
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->string('admission_no')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->enum('gender', ['M', 'F', 'O']);
            $table->date('date_of_birth');
            $table->string('nationality')->nullable();
            $table->string('state_of_origin')->nullable();
            $table->string('lga_of_origin')->nullable();
            $table->string('house')->default('none');
            $table->string('club')->default('none');
            $table->uuid('current_session_id');
            $table->uuid('current_term_id');
            $table->uuid('school_class_id');
            $table->uuid('class_arm_id');
            $table->uuid('class_section_id')->nullable();
            $table->uuid('parent_id');
            $table->date('admission_date');
            $table->string('photo_url', 512)->nullable();
            $table->string('address')->nullable();
            $table->text('medical_information')->nullable();
            $table->enum('status', ['active', 'inactive', 'graduated', 'withdrawn'])->default('active');
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('current_session_id')->references('id')->on('sessions')->onDelete('restrict');
            $table->foreign('current_term_id')->references('id')->on('terms')->onDelete('restrict');
            $table->foreign('school_class_id')->references('id')->on('classes')->onDelete('restrict');
            $table->foreign('class_arm_id')->references('id')->on('class_arms')->onDelete('restrict');
            $table->foreign('class_section_id')->references('id')->on('class_sections')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('parents')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('students');
    }
};
