<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('term_summaries', function (Blueprint $table) {
            if (! Schema::hasColumn('term_summaries', 'principal_comment')) {
                $table->text('principal_comment')->nullable()->default('This student is hardworking.')->after('overall_comment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('term_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('term_summaries', 'principal_comment')) {
                $table->dropColumn('principal_comment');
            }
        });
    }
};
