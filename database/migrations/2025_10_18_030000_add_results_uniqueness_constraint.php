<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            if (! Schema::hasColumn('results', 'component_slot')) {
                $table->char('component_slot', 36)
                    ->default(self::NULL_COMPONENT_UUID)
                    ->after('assessment_component_id');
            }
        });

        DB::table('results')->whereNull('assessment_component_id')->update([
            'component_slot' => self::NULL_COMPONENT_UUID,
        ]);

        DB::table('results')->whereNotNull('assessment_component_id')->update([
            'component_slot' => DB::raw('assessment_component_id'),
        ]);

        Schema::table('results', function (Blueprint $table) {
            $indexName = 'results_unique_score_per_context';
            $indexes = $this->listIndexes('results');

            if (! in_array($indexName, $indexes, true)) {
                $table->unique(
                    ['student_id', 'subject_id', 'session_id', 'term_id', 'component_slot'],
                    $indexName
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $indexName = 'results_unique_score_per_context';
            $indexes = $this->listIndexes('results');

            if (in_array($indexName, $indexes, true)) {
                $table->dropUnique($indexName);
            }
        });

        Schema::table('results', function (Blueprint $table) {
            if (Schema::hasColumn('results', 'component_slot')) {
                $table->dropColumn('component_slot');
            }
        });
    }

    private function listIndexes(string $table): array
    {
        $schema = Schema::getConnection()->getDatabaseName();

        $rows = Schema::getConnection()->select("
            SELECT DISTINCT index_name
            FROM information_schema.statistics
            WHERE table_schema = ?
              AND table_name = ?
        ", [$schema, $table]);

        return collect($rows)->pluck('index_name')->map(fn ($name) => (string) $name)->all();
    }

    private const NULL_COMPONENT_UUID = '00000000-0000-0000-0000-000000000000';
};
