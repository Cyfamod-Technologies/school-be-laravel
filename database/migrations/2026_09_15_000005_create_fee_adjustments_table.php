<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discounts, surcharges and waivers applied to a single bill line.
 *
 * This table is the source of truth; student_bill_items.discount_amount and
 * .surcharge_amount are projections of it. Keeping the individual rows is what
 * makes "who reduced this bill, by how much, and why" answerable months later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('student_bill_item_id');

            $table->string('type', 16);
            $table->decimal('amount', 14, 2);
            $table->string('reason');

            $table->uuid('created_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->uuid('reversed_by')->nullable();

            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('student_bill_item_id')->references('id')->on('student_bill_items')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reversed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['student_bill_item_id', 'reversed_at'], 'fee_adjustments_item_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_adjustments');
    }
};
