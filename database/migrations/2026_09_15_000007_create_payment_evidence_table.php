<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uploaded proof of an offline payment -- transfer receipts, deposit slips,
 * POS printouts.
 *
 * The `disk` column exists to make the privacy requirement explicit and
 * auditable: unlike student photos and school logos, these files name a
 * student and an amount and must live on a private disk, reachable only
 * through an ownership-checked controller route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');

            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');

            $table->string('uploaded_by_type', 16)->nullable();
            $table->uuid('uploaded_by_id')->nullable();

            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_evidence');
    }
};
