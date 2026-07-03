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
        Schema::create('midterm_student_additions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('term_id');
            $table->uuid('school_id');
            $table->uuid('student_id');
            $table->uuid('invoice_id')->nullable();
            $table->enum('status', ['pending_payment', 'paid'])->default('pending_payment');
            $table->decimal('price_per_student', 10, 2);
            $table->date('admission_date');
            $table->timestamps();

            $table->foreign('term_id')->references('id')->on('terms')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
            $table->index(['term_id', 'school_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('midterm_student_additions');
    }
};
