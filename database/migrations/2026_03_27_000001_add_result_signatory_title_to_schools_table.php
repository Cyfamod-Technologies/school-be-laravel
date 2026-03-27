<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'result_signatory_title')) {
                $table->string('result_signatory_title', 20)
                    ->default('principal')
                    ->after('result_comment_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'result_signatory_title')) {
                $table->dropColumn('result_signatory_title');
            }
        });
    }
};
