<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_pins', function (Blueprint $table) {
            if (! Schema::hasColumn('result_pins', 'max_usage')) {
                $table->unsignedInteger('max_usage')->nullable()->after('use_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('result_pins', function (Blueprint $table) {
            if (Schema::hasColumn('result_pins', 'max_usage')) {
                $table->dropColumn('max_usage');
            }
        });
    }
};
