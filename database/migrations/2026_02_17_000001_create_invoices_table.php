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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('term_id')->nullable();
            $table->enum('invoice_type', ['original', 'midterm_addition'])->default('original');
            $table->integer('student_count');
            $table->decimal('price_per_student', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['draft', 'sent', 'paid', 'partial'])->default('draft');
            $table->date('due_date');
            $table->uuid('reference_invoice_id')->nullable()->comment('Links to original invoice if midterm');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('term_id')->references('id')->on('terms')->onDelete('set null');
            $table->index('school_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
