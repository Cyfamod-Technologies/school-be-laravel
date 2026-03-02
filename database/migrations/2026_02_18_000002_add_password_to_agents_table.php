<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agents') || Schema::hasColumn('agents', 'password')) {
            return;
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('agents') || ! Schema::hasColumn('agents', 'password')) {
            return;
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
