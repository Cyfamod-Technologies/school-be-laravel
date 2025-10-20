<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_components', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_components', 'subject_id')) {
                $table->uuid('subject_id')->nullable()->after('term_id');
                $table->foreign('subject_id')
                    ->references('id')
                    ->on('subjects')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('assessment_components', 'order')) {
                $table->integer('order')->default(0);
            }
        });

        Schema::table('assessment_components', function (Blueprint $table) {
            $table->unique([
                'school_id',
                'session_id',
                'term_id',
                'subject_id',
                'name',
            ], 'assessment_components_unique_per_context');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_components', function (Blueprint $table) {
            if ($this->hasIndex('assessment_components', 'assessment_components_unique_per_context')) {
                $table->dropUnique('assessment_components_unique_per_context');
            }

            if (Schema::hasColumn('assessment_components', 'subject_id')) {
                $tableName = 'assessment_components';
                foreach ($this->foreignKeysForColumn($tableName, 'subject_id') as $foreignKey) {
                    $table->dropForeign($foreignKey);
                }
                $table->dropColumn('subject_id');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $schema = Schema::getConnection()->getDatabaseName();

        $result = Schema::getConnection()->selectOne('
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = ?
              AND table_name = ?
              AND index_name = ?
            LIMIT 1
        ', [$schema, $table, $index]);

        return $result !== null;
    }

    private function foreignKeysForColumn(string $table, string $column): array
    {
        $schema = Schema::getConnection()->getDatabaseName();
        $rows = Schema::getConnection()->select(
            "SELECT constraint_name
             FROM information_schema.key_column_usage
             WHERE table_schema = ?
               AND table_name = ?
               AND column_name = ?
               AND referenced_table_name IS NOT NULL",
            [$schema, $table, $column]
        );

        return collect($rows)
            ->pluck('constraint_name')
            ->map(fn ($name) => (string) $name)
            ->all();
    }
};
