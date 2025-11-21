<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('house')->nullable()->default(null)->change();
            $table->string('club')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('house')->nullable(false)->default('none')->change();
            $table->string('club')->nullable(false)->default('none')->change();
        });
    }
};
