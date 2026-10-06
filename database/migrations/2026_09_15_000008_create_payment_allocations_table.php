<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a verified payment was applied across the lines of a bill.
 *
 * Allocations explain which fee each naira covered; they are NOT what the
 * student's balance is computed from. Balance comes from verified payments, so
 * an approved-but-not-fully-allocated payment still credits the student in
 * full and leaves the remainder as an unallocated credit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->uuid('student_bill_item_id');
            $table->decimal('amount', 14, 2);
            $table->uuid('allocated_by')->nullable();
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->foreign('student_bill_item_id')->references('id')->on('student_bill_items')->cascadeOnDelete();
            $table->foreign('allocated_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['payment_id', 'student_bill_item_id'], 'payment_allocations_payment_item_unique');
            $table->index('student_bill_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
