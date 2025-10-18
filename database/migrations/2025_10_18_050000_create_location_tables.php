<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('code', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_id');
            $table->string('name');
            $table->string('code', 10)->nullable();
            $table->timestamps();

            $table->foreign('country_id')
                ->references('id')
                ->on('countries')
                ->cascadeOnDelete();

            $table->unique(['country_id', 'name']);
            $table->index('country_id');
        });

        Schema::create('local_government_areas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('state_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('state_id')
                ->references('id')
                ->on('states')
                ->cascadeOnDelete();

            $table->unique(['state_id', 'name']);
            $table->index('state_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_government_areas');
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
    }
};

