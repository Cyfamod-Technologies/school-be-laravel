<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'result_pdf_requires_pin')) {
                $table->boolean('result_pdf_requires_pin')
                    ->default(true)
                    ->after('result_allow_shared_pin_access');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'result_pdf_requires_pin')) {
                $table->dropColumn('result_pdf_requires_pin');
            }
        });
    }
};
