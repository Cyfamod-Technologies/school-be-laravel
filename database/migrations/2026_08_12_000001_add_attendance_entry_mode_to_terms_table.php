<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->string('attendance_entry_mode', 20)
                ->default('daily')
                ->after('use_position_ranges');
        });

        // Preserve the attendance workflow used by schools before Daily Register
        // was introduced. Terms created after this migration retain the `daily`
        // database default.
        DB::table('terms')->update(['attendance_entry_mode' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn('attendance_entry_mode');
        });
    }
};
