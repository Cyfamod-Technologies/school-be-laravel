<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('user_id');
            $table->text('user_message');
            $table->text('assistant_reply');
            $table->string('intent')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'created_at'], 'ai_chat_logs_school_created_at_index');
            $table->index(['user_id', 'created_at'], 'ai_chat_logs_user_created_at_index');
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_logs');
    }
};
