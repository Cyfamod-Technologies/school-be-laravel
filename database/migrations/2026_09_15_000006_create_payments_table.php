<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every payment, at every stage of its life.
 *
 * A student's submitted evidence and a verified payment live in the same table
 * separated by `status`, not in two tables: the distinction the spec cares
 * about is semantic, and one table with an explicit lifecycle avoids the
 * dual-write consistency problem that two tables would create. The admin's
 * "Payment Submissions" and "Verified Payments" screens are two filters over
 * this table.
 *
 * Nothing here is ever hard-deleted. A mistaken verification is undone with
 * status = 'reversed' plus a reason, which reverses its allocations too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('student_id');
            $table->uuid('session_id');
            $table->uuid('term_id');
            $table->uuid('student_bill_id')->nullable();

            // Ours, minted on submission, shown to the payer.
            $table->string('reference', 32);
            $table->string('receipt_number', 32)->nullable();
            // Theirs: the transaction/teller number off the bank receipt. Not
            // unique -- a payer can mistype it, and two banks can collide.
            $table->string('payer_reference', 100)->nullable();

            $table->decimal('amount', 14, 2);
            $table->string('method', 24);
            $table->date('paid_at');
            $table->text('note')->nullable();

            $table->string('source', 24);
            $table->string('status', 24);

            // Nullable morph: a student, a parent acting as one, or an admin.
            $table->string('submitted_by_type', 16)->nullable();
            $table->uuid('submitted_by_id')->nullable();

            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('reversed_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();

            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            $table->foreign('student_bill_id')->references('id')->on('student_bills')->nullOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reversed_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['school_id', 'reference'], 'payments_school_reference_unique');
            $table->unique(['school_id', 'receipt_number'], 'payments_school_receipt_unique');
            $table->index(['school_id', 'status'], 'payments_school_status_index');
            $table->index(['student_id', 'status'], 'payments_student_status_index');
            $table->index(['student_bill_id', 'status'], 'payments_bill_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
