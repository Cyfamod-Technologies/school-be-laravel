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
            $table->boolean('use_position_ranges')->default(false)->change();
        });

        DB::table('terms')->update(['use_position_ranges' => false]);
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->boolean('use_position_ranges')->default(true)->change();
        });
    }
};
