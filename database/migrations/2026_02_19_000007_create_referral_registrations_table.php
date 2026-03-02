<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referral_id');
            $table->uuid('school_id');
            $table->timestamp('registered_at')->nullable();
            $table->integer('payment_count')->default(0);
            $table->decimal('first_payment_amount', 10, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('active_at')->nullable();
            $table->timestamps();

            $table->unique(['referral_id', 'school_id']);
            $table->unique('school_id');
            $table->index('referral_id');
            $table->index('registered_at');

            $table->foreign('referral_id')->references('id')->on('referrals')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_registrations');
    }
};

