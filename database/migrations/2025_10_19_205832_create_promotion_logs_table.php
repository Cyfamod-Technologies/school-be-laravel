<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('from_session_id')->nullable();
            $table->uuid('to_session_id');
            $table->uuid('from_class_id')->nullable();
            $table->uuid('to_class_id');
            $table->uuid('from_class_arm_id')->nullable();
            $table->uuid('to_class_arm_id')->nullable();
            $table->uuid('from_section_id')->nullable();
            $table->uuid('to_section_id')->nullable();
            $table->uuid('performed_by');
            $table->timestamp('promoted_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('from_session_id')->references('id')->on('sessions')->nullOnDelete();
            $table->foreign('to_session_id')->references('id')->on('sessions')->cascadeOnDelete();
            $table->foreign('from_class_id')->references('id')->on('classes')->nullOnDelete();
            $table->foreign('to_class_id')->references('id')->on('classes')->cascadeOnDelete();
            $table->foreign('from_class_arm_id')->references('id')->on('class_arms')->nullOnDelete();
            $table->foreign('to_class_arm_id')->references('id')->on('class_arms')->nullOnDelete();
            $table->foreign('from_section_id')->references('id')->on('class_sections')->nullOnDelete();
            $table->foreign('to_section_id')->references('id')->on('class_sections')->nullOnDelete();
            $table->foreign('performed_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_logs');
    }
};
