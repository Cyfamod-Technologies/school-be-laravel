<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('agent_commissions')) {
            Schema::create('agent_commissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('agent_id');
                $table->uuid('referral_id');
                $table->uuid('school_id');
                $table->uuid('invoice_id')->nullable();
                $table->integer('payment_number')->comment('Which payment triggered this commission (1st, 2nd, etc)');
                $table->decimal('commission_amount', 10, 2);
                $table->enum('status', ['pending', 'approved', 'paid'])->default('pending');
                $table->uuid('payout_id')->nullable();
                $table->timestamps();

                $table->index(['agent_id', 'status']);
                $table->index('referral_id');
            });
        } else {
            Schema::table('agent_commissions', function (Blueprint $table) {
                if (! Schema::hasColumn('agent_commissions', 'agent_id')) {
                    $table->uuid('agent_id');
                }
                if (! Schema::hasColumn('agent_commissions', 'referral_id')) {
                    $table->uuid('referral_id');
                }
                if (! Schema::hasColumn('agent_commissions', 'school_id')) {
                    $table->uuid('school_id');
                }
                if (! Schema::hasColumn('agent_commissions', 'invoice_id')) {
                    $table->uuid('invoice_id')->nullable();
                }
                if (! Schema::hasColumn('agent_commissions', 'payment_number')) {
                    $table->integer('payment_number')->comment('Which payment triggered this commission (1st, 2nd, etc)');
                }
                if (! Schema::hasColumn('agent_commissions', 'commission_amount')) {
                    $table->decimal('commission_amount', 10, 2);
                }
                if (! Schema::hasColumn('agent_commissions', 'status')) {
                    $table->enum('status', ['pending', 'approved', 'paid'])->default('pending');
                }
                if (! Schema::hasColumn('agent_commissions', 'payout_id')) {
                    $table->uuid('payout_id')->nullable();
                }
                if (! Schema::hasColumn('agent_commissions', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (! Schema::hasColumn('agent_commissions', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }

        $this->normalizeTimestampColumns();
        $this->ensureIndexes();
        $this->ensureForeignKeys();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_commissions');
    }

    private function normalizeTimestampColumns(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE agent_commissions
            MODIFY created_at TIMESTAMP NULL DEFAULT NULL,
            MODIFY updated_at TIMESTAMP NULL DEFAULT NULL');
    }

    private function ensureIndexes(): void
    {
        if (! $this->hasIndex('agent_commissions', 'agent_commissions_agent_id_status_index')) {
            Schema::table('agent_commissions', function (Blueprint $table) {
                $table->index(['agent_id', 'status']);
            });
        }

        if (! $this->hasIndex('agent_commissions', 'agent_commissions_referral_id_index')) {
            Schema::table('agent_commissions', function (Blueprint $table) {
                $table->index('referral_id');
            });
        }
    }

    private function ensureForeignKeys(): void
    {
        if (
            Schema::hasTable('agents')
            && Schema::hasColumn('agent_commissions', 'agent_id')
            && ! $this->hasForeignKey('agent_commissions', 'agent_commissions_agent_id_foreign')
        ) {
            Schema::table('agent_commissions', function (Blueprint $table) {
                $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            });
        }

        if (
            Schema::hasTable('referrals')
            && Schema::hasColumn('agent_commissions', 'referral_id')
            && ! $this->hasForeignKey('agent_commissions', 'agent_commissions_referral_id_foreign')
        ) {
            Schema::table('agent_commissions', function (Blueprint $table) {
                $table->foreign('referral_id')->references('id')->on('referrals')->onDelete('cascade');
            });
        }

        if (
            Schema::hasTable('schools')
            && Schema::hasColumn('agent_commissions', 'school_id')
            && ! $this->hasForeignKey('agent_commissions', 'agent_commissions_school_id_foreign')
        ) {
            Schema::table('agent_commissions', function (Blueprint $table) {
                $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            });
        }

        if (
            Schema::hasTable('invoices')
            && Schema::hasColumn('agent_commissions', 'invoice_id')
            && ! $this->hasForeignKey('agent_commissions', 'agent_commissions_invoice_id_foreign')
        ) {
            Schema::table('agent_commissions', function (Blueprint $table) {
                $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            });
        }
    }

    private function hasForeignKey(string $table, string $keyName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        $schema = Schema::getConnection()->getDatabaseName();

        $result = Schema::getConnection()->selectOne('
            SELECT 1
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
            LIMIT 1
        ', [$schema, $table, $keyName]);

        return $result !== null;
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        $schema = Schema::getConnection()->getDatabaseName();

        $result = Schema::getConnection()->selectOne('
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
            LIMIT 1
        ', [$schema, $table, $indexName]);

        return $result !== null;
    }
};
