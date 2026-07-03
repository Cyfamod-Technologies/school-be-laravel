<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('school_websites', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('school_id')->unique();

            $table->unsignedTinyInteger('contract_version')
                ->default(1);

            $table->string('status', 30)
                ->default('draft');

            $table->string('theme_key')
                ->default('kidza-home-2');

            $table->json('branding')->nullable();
            $table->json('seo')->nullable();
            $table->json('header')->nullable();
            $table->json('hero')->nullable();
            $table->json('highlights')->nullable();
            $table->json('about')->nullable();
            $table->json('programmes')->nullable();
            $table->json('admissions')->nullable();
            $table->json('contact')->nullable();
            $table->json('social_links')->nullable();
            $table->json('enabled_sections')->nullable();

            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table
                ->foreign('school_id')
                ->references('id')
                ->on('schools')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_websites');
    }
};
