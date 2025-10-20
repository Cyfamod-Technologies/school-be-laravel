<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // Drop Foreign Keys and Indexes if Columns exist
        Schema::table('assessment_components', function (Blueprint $table) {
            $tableName = Schema::getConnection()->getTablePrefix() . 'assessment_components';
            
            // Check and drop foreign keys for session_id
            if (Schema::hasColumn('assessment_components', 'session_id')) {
                foreach ($this->foreignKeysForColumn($tableName, 'session_id') as $foreignName) {
                    try {
                        $table->dropForeign($foreignName);
                    } catch (\Exception $e) {
                        // ignore if constraint doesn't exist
                    }
                }
                
                // Check and drop indexes for session_id
                foreach ($this->indexesForColumn($tableName, 'session_id') as $indexName) {
                    try {
                        $table->dropIndex($indexName);
                    } catch (\Exception $e) {
                        // ignore if index doesn't exist
                    }
                }
            }

            // Check and drop foreign keys for term_id
            if (Schema::hasColumn('assessment_components', 'term_id')) {
                foreach ($this->foreignKeysForColumn($tableName, 'term_id') as $foreignName) {
                    try {
                        $table->dropForeign($foreignName);
                    } catch (\Exception $e) {
                        // ignore if constraint doesn't exist
                    }
                }

                // Check and drop indexes for term_id
                foreach ($this->indexesForColumn($tableName, 'term_id') as $indexName) {
                    try {
                        $table->dropIndex($indexName);
                    } catch (\Exception $e) {
                        // ignore if index doesn't exist
                    }
                }
            }
        });

        // Drop unique indexes if they exist
        Schema::table('assessment_components', function (Blueprint $table) {
            if ($this->hasIndex('assessment_components', 'assessment_components_unique_per_context_no_subject')) {
                $table->dropUnique('assessment_components_unique_per_context_no_subject');
            }

            if ($this->hasIndex('assessment_components', 'assessment_components_unique_per_context')) {
                $table->dropUnique('assessment_components_unique_per_context');
            }
        });

        // Drop columns session_id and term_id if they exist
        Schema::table('assessment_components', function (Blueprint $table) {
            if (Schema::hasColumn('assessment_components', 'session_id')) {
                $table->dropColumn('session_id');
            }

            if (Schema::hasColumn('assessment_components', 'term_id')) {
                $table->dropColumn('term_id');
            }
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        // Add columns session_id and term_id back
        Schema::table('assessment_components', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_components', 'session_id')) {
                $table->uuid('session_id')->nullable()->after('school_id');
            }

            if (! Schema::hasColumn('assessment_components', 'term_id')) {
                $table->uuid('term_id')->nullable()->after('session_id');
            }
        });

        // Add foreign keys for session_id and term_id
        Schema::table('assessment_components', function (Blueprint $table) {
            if (Schema::hasColumn('assessment_components', 'session_id')) {
                $table->foreign('session_id')
                    ->references('id')
                    ->on('sessions')
                    ->nullOnDelete();
            }

            if (Schema::hasColumn('assessment_components', 'term_id')) {
                $table->foreign('term_id')
                    ->references('id')
                    ->on('terms')
                    ->nullOnDelete();
            }
        });

        // Recreate unique indexes if they were dropped
        Schema::table('assessment_components', function (Blueprint $table) {
            if (! $this->hasIndex('assessment_components', 'assessment_components_unique_per_context_no_subject')) {
                $table->unique(
                    ['school_id', 'session_id', 'term_id', 'name'],
                    'assessment_components_unique_per_context_no_subject'
                );
            }
        });

        Schema::enableForeignKeyConstraints();
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $schema = Schema::getConnection()->getDatabaseName();
        $rows = Schema::getConnection()->select(
            "SELECT DISTINCT index_name
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?",
            [$schema, $table, $indexName]
        );

        return ! empty($rows);
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

    private function indexesForColumn(string $table, string $column): array
    {
        $schema = Schema::getConnection()->getDatabaseName();

        $rows = Schema::getConnection()->select(
            "SELECT DISTINCT index_name
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND column_name = ?
               AND index_name <> 'PRIMARY'",
            [$schema, $table, $column]
        );

        return collect($rows)
            ->pluck('index_name')
            ->map(fn ($name) => (string) $name)
            ->all();
    }
};
