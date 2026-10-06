<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The individual lines that make up a student's bill.
 *
 * `name` is snapshotted from the fee item so a later rename of "Tuition" does
 * not retroactively change what an already-paid bill says it was for, and
 * `fee_structure_id` is nullable + nullOnDelete so deleting an assignment can
 * never destroy a line that has money allocated against it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_bill_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('student_bill_id');
            $table->uuid('fee_structure_id')->nullable();
            $table->uuid('fee_item_id')->nullable();

            $table->string('name');
            $table->string('description')->nullable();
            $table->string('source', 16);

            $table->decimal('amount', 14, 2)->default(0);
            // Projections of fee_adjustments, recomputed in the same
            // transaction as any adjustment write.
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('surcharge_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);

            // A line whose assignment was withdrawn after money landed on it
            // is retired, never deleted.
            $table->boolean('is_removed')->default(false);
            $table->string('removed_reason')->nullable();

            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('student_bill_id')->references('id')->on('student_bills')->cascadeOnDelete();
            $table->foreign('fee_structure_id')->references('id')->on('fee_structures')->nullOnDelete();
            $table->foreign('fee_item_id')->references('id')->on('fee_items')->nullOnDelete();

            // Keeps bill generation idempotent. Manually added lines carry a
            // NULL fee_structure_id and MariaDB allows any number of those.
            $table->unique(['student_bill_id', 'fee_structure_id'], 'student_bill_items_bill_structure_unique');
            $table->index(['school_id', 'fee_item_id'], 'student_bill_items_school_item_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_bill_items');
    }
};
