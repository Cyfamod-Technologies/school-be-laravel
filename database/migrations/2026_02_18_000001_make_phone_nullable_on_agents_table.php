<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agents') || ! Schema::hasColumn('agents', 'phone')) {
            return;
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('agents') || ! Schema::hasColumn('agents', 'phone')) {
            return;
        }

        DB::table('agents')
            ->whereNull('phone')
            ->update(['phone' => '']);

        Schema::table('agents', function (Blueprint $table) {
            $table->string('phone', 20)->nullable(false)->change();
        });
    }
};

