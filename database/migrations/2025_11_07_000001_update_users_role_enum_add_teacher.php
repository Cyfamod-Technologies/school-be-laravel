<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Extend the enum to include 'teacher'
        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('staff','parent','super_admin','accountant','admin','teacher') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous enum (may fail if rows contain 'teacher')
        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('staff','parent','super_admin','accountant','admin') NOT NULL");
    }
};


