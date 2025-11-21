<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'parent_id')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            if ($this->foreignKeyExists('students', 'students_parent_id_foreign')) {
                $table->dropForeign('students_parent_id_foreign');
            }
        });

        $driver = DB::getDriverName();
        $table = DB::getTablePrefix() . 'students';

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY parent_id CHAR(36) NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN parent_id DROP NOT NULL");
        } else {
            Schema::table('students', function (Blueprint $table) {
                $table->uuid('parent_id')->nullable()->change();
            });
        }

        Schema::table('students', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('parents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('students', 'parent_id')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            if ($this->foreignKeyExists('students', 'students_parent_id_foreign')) {
                $table->dropForeign('students_parent_id_foreign');
            }
        });

        $driver = DB::getDriverName();
        $table = DB::getTablePrefix() . 'students';

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY parent_id CHAR(36) NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN parent_id SET NOT NULL");
        } else {
            Schema::table('students', function (Blueprint $table) {
                $table->uuid('parent_id')->nullable(false)->change();
            });
        }

        Schema::table('students', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('parents')
                ->restrictOnDelete();
        });
    }

    private function foreignKeyExists(string $table, string $key): bool
    {
        $database = DB::getDatabaseName();
        $prefixedTable = DB::getTablePrefix() . $table;

        $result = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$database, $prefixedTable, $key]
        );

        return $result !== null;
    }
};
