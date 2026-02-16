<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->makeNullableWithNullOnDelete('students', 'class_arm_id', 'class_arms');
        $this->makeNullableWithNullOnDelete('class_teachers', 'class_arm_id', 'class_arms');
    }

    public function down(): void
    {
        // No-op: restoring NOT NULL safely depends on existing production data.
    }

    private function makeNullableWithNullOnDelete(string $tableName, string $columnName, string $referencedTable): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        $foreignKeyName = $this->getForeignKeyName($tableName, $columnName);

        if ($foreignKeyName) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKeyName) {
                $table->dropForeign($foreignKeyName);
            });
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName) {
            $table->uuid($columnName)->nullable()->change();
        });

        if (! Schema::hasTable($referencedTable)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName, $referencedTable) {
            $table->foreign($columnName)
                ->references('id')
                ->on($referencedTable)
                ->nullOnDelete();
        });
    }

    private function getForeignKeyName(string $tableName, string $columnName): ?string
    {
        $databaseName = DB::getDatabaseName();

        $record = DB::selectOne(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$databaseName, $tableName, $columnName]
        );

        return $record?->CONSTRAINT_NAME ?? null;
    }
};
