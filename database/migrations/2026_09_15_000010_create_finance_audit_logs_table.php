<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who changed what, when, and what it looked like before and after.
 *
 * Separate from the existing `audit_logs` table on purpose: that one hard-keys
 * its actor to `users`, and half the actors here are students submitting their
 * own payments. A morph actor plus before/after JSON is what financial dispute
 * resolution actually needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');

            $table->string('actor_type', 16);
            $table->uuid('actor_id')->nullable();
            $table->string('actor_name')->nullable();

            $table->string('action', 64);
            $table->string('subject_type', 64);
            $table->uuid('subject_id')->nullable();

            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();

            $table->index(['school_id', 'created_at'], 'finance_audit_logs_school_time_index');
            $table->index(['subject_type', 'subject_id'], 'finance_audit_logs_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_audit_logs');
    }
};
