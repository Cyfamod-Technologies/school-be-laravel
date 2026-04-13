<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'result_enable_session_print')) {
                $table->boolean('result_enable_session_print')
                    ->default(false)
                    ->after('result_allow_shared_pin_access');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'result_enable_session_print')) {
                $table->dropColumn('result_enable_session_print');
            }
        });
    }
};
