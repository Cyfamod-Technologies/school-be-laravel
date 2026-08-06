<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_pins', function (Blueprint $table) {
            if (! Schema::hasColumn('result_pins', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('revoked_at')->index();
            }

            if (! Schema::hasColumn('result_pins', 'sent_by')) {
                $table->uuid('sent_by')->nullable()->after('sent_at');
                $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('result_pins', function (Blueprint $table) {
            if (Schema::hasColumn('result_pins', 'sent_by')) {
                $table->dropForeign(['sent_by']);
                $table->dropColumn('sent_by');
            }

            if (Schema::hasColumn('result_pins', 'sent_at')) {
                $table->dropColumn('sent_at');
            }
        });
    }
};
