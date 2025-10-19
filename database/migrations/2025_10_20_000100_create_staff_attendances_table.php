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
        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('staff_id');
            $table->uuid('school_id');
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'on_leave'])->default('present');
            $table->string('branch_name')->nullable();
            $table->uuid('recorded_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('staff_id')
                ->references('id')
                ->on('staff')
                ->cascadeOnDelete();

            $table->foreign('school_id')
                ->references('id')
                ->on('schools')
                ->cascadeOnDelete();

            $table->foreign('recorded_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique(['staff_id', 'date'], 'staff_attendances_staff_date_unique');
            $table->index(['date', 'school_id'], 'staff_attendances_date_school_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_attendances');
    }
};
