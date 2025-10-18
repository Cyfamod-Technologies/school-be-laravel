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
                $table->dropForeign(['subject_id']);
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
};
