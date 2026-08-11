<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->string('token', 512)->unique();
            $table->string('device_id', 191)->nullable()->index();
            $table->string('platform', 20);
            $table->string('app_version', 50)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->index(['student_id', 'platform']);
        });

        Schema::create('student_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->string('type', 50);
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('dedupe_key', 191)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->index(['student_id', 'created_at']);
            $table->index(['student_id', 'read_at']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedInteger('notification_revision')->default(0)->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('notification_revision');
        });

        Schema::dropIfExists('student_notifications');
        Schema::dropIfExists('student_devices');
    }
};
