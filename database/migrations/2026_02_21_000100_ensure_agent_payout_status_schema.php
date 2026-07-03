<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agent_payouts')) {
            return;
        }

        Schema::table('agent_payouts', function (Blueprint $table) {
            if (! Schema::hasColumn('agent_payouts', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('requested_at');
            }

            if (! Schema::hasColumn('agent_payouts', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('agent_payouts', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('processed_at');
            }

            if (! Schema::hasColumn('agent_payouts', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('completed_at');
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('agent_payouts')->where('status', 'paid')->update(['status' => 'completed']);
        DB::table('agent_payouts')->where('status', 'processed')->update(['status' => 'processing']);
        DB::table('agent_payouts')->where('status', 'done')->update(['status' => 'completed']);
        DB::table('agent_payouts')->where('status', 'rejected')->update(['status' => 'failed']);
        DB::table('agent_payouts')->where('status', 'declined')->update(['status' => 'failed']);

        DB::statement("
            ALTER TABLE agent_payouts
            MODIFY status ENUM('pending','approved','processing','completed','failed')
            NOT NULL DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        // Intentionally no-op: normalization migration.
    }
};

