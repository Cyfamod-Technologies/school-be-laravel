<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('acronym', 20)->nullable();
            $table->unsignedInteger('code_sequence')->nullable()->unique('schools_code_sequence_unique');
            $table->string('slug')->unique();
            $table->string('subdomain')->unique();
            $table->text('address');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('logo_url', 512)->nullable();
            $table->date('established_at')->nullable();
            $table->string('owner_name')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('schools');
    }
};
