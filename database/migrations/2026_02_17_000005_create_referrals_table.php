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
        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id');
            $table->uuid('school_id')->nullable();
            $table->string('referral_code', 50)->unique();
            $table->string('referral_link', 500);
            $table->enum('status', ['visited', 'registered', 'paid', 'active'])->default('visited');
            $table->integer('payment_count')->default(0)->comment('How many payments have triggered commission');
            $table->boolean('commission_limit_reached')->default(false);
            $table->decimal('first_payment_amount', 10, 2)->nullable();
            $table->timestamp('visited_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('active_at')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('set null');
            $table->index('agent_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
