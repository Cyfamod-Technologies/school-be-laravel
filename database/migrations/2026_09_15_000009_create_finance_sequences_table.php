<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-school counters for payment references (CYF-10483) and receipt numbers.
 *
 * A counter row read with lockForUpdate() beats `max(reference) + 1`, which
 * races the moment two parents submit evidence in the same second.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->string('kind', 32);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->unique(['school_id', 'kind'], 'finance_sequences_school_kind_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_sequences');
    }
};
