<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'term_school_opened_days')) {
                $table->unsignedSmallInteger('term_school_opened_days')
                    ->nullable()
                    ->after('student_portal_link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'term_school_opened_days')) {
                $table->dropColumn('term_school_opened_days');
            }
        });
    }
};
