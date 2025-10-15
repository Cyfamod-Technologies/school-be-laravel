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
        Schema::create('staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('user_id')->unique();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->string('role');
            $table->enum('gender', ['male', 'female', 'others']);
            $table->string('address')->nullable();
            $table->string('qualifications')->nullable();
            $table->date('employment_start_date')->nullable();
            $table->string('photo_url', 512)->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['school_id', 'email']);
            $table->unique(['school_id', 'phone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
