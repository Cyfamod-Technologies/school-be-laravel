<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This migration is intended to remove session_id and term_id columns
        // from assessment_components table, but they don't exist in the current schema.
        // We'll log this information and continue without making changes.

        \Log::info('Migration 2025_10_19_000000: Skipping removal of session_id and term_id columns from assessment_components as they do not exist.');

        // No action needed as the columns don't exist
    }

    public function down(): void
    {
        // Since the up() method doesn't make any changes (columns don't exist),
        // there's nothing to revert in the down() method
        \Log::info('Migration 2025_10_19_000000 down(): No action needed as the columns were not removed in the up() method.');

        // No action needed
    }
};
