<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'current_session_id')) {
                $table->uuid('current_session_id')->nullable()->after('status');
            }

            if (! Schema::hasColumn('schools', 'current_term_id')) {
                $table->uuid('current_term_id')->nullable()->after('current_session_id');
            }
        });

        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'current_session_id')) {
                $table->foreign('current_session_id', 'schools_current_session_id_foreign')
                    ->references('id')
                    ->on('sessions')
                    ->nullOnDelete();
            }

            if (Schema::hasColumn('schools', 'current_term_id')) {
                $table->foreign('current_term_id', 'schools_current_term_id_foreign')
                    ->references('id')
                    ->on('terms')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'current_term_id')) {
                if ($this->hasForeignKey('schools', 'schools_current_term_id_foreign')) {
                    $table->dropForeign('schools_current_term_id_foreign');
                }
                $table->dropColumn('current_term_id');
            }

            if (Schema::hasColumn('schools', 'current_session_id')) {
                if ($this->hasForeignKey('schools', 'schools_current_session_id_foreign')) {
                    $table->dropForeign('schools_current_session_id_foreign');
                }
                $table->dropColumn('current_session_id');
            }
        });
    }

    private function hasForeignKey(string $table, string $keyName): bool
    {
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
};

