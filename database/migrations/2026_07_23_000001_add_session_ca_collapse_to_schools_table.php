<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'result_collapse_session_ca')) {
                $table->boolean('result_collapse_session_ca')
                    ->default(false)
                    ->after('result_enable_session_print');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'result_collapse_session_ca')) {
                $table->dropColumn('result_collapse_session_ca');
            }
        });
    }
};
