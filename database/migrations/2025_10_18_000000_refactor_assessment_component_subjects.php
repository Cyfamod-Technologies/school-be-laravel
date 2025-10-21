<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (! Schema::hasTable('assessment_component_subject')) {
            Schema::create('assessment_component_subject', function (Blueprint $table) {
                $table->uuid('assessment_component_id');
                $table->uuid('subject_id');

                $table->primary(['assessment_component_id', 'subject_id'], 'assessment_component_subject_primary');

                $table->foreign('assessment_component_id', 'assessment_component_subject_component_fk')
                    ->references('id')
                    ->on('assessment_components')
                    ->cascadeOnDelete();

                $table->foreign('subject_id', 'assessment_component_subject_subject_fk')
                    ->references('id')
                    ->on('subjects')
                    ->cascadeOnDelete();
            });
        }

        $hasSubjectColumn = $this->tableHasColumn('assessment_components', 'subject_id');

        if ($hasSubjectColumn) {
            $components = DB::table('assessment_components')
                ->select('id', 'school_id', 'session_id', 'term_id', 'subject_id', 'name', 'weight', 'order', 'label', 'created_at')
                ->orderBy('created_at')
                ->get();

            $groups = [];

            foreach ($components as $component) {
                $key = implode('|', [
                    $component->school_id,
                    $component->session_id,
                    $component->term_id,
                    $component->name,
                    number_format((float) $component->weight, 2, '.', ''),
                    (string) $component->order,
                    (string) ($component->label ?? ''),
                ]);

                $groups[$key][] = $component;
            }

            foreach ($groups as $groupComponents) {
                $primary = $groupComponents[0];
                $handledSubjectIds = [];

                foreach ($groupComponents as $component) {
                    if (! empty($component->subject_id) && ! in_array($component->subject_id, $handledSubjectIds, true)) {
                        DB::table('assessment_component_subject')->insertOrIgnore([
                            'assessment_component_id' => $primary->id,
                            'subject_id' => $component->subject_id,
                        ]);
                        $handledSubjectIds[] = $component->subject_id;
                    }

                    if ($component->id !== $primary->id) {
                        DB::table('results')
                            ->where('assessment_component_id', $component->id)
                            ->update(['assessment_component_id' => $primary->id]);
                    }
                }

                $duplicateIds = [];

                foreach ($groupComponents as $component) {
                    if ($component->id !== $primary->id) {
                        $duplicateIds[] = $component->id;
                    }
                }

                if (! empty($duplicateIds)) {
                    DB::table('assessment_components')
                        ->whereIn('id', $duplicateIds)
                        ->delete();
                }
            }
        }

        $this->dropAssessmentComponentSubjectForeignKey();

        Schema::table('assessment_components', function (Blueprint $table) {
            if ($this->hasIndex('assessment_components', 'assessment_components_unique_per_context')) {
                $table->dropUnique('assessment_components_unique_per_context');
            }
        });

        if ($hasSubjectColumn) {
            try {
                Schema::table('assessment_components', function (Blueprint $table) {
                    $table->dropColumn('subject_id');
                });
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), "doesn't exist") === false) {
                    throw $e;
                }
            }
        }

        Schema::table('assessment_components', function (Blueprint $table) {
            $table->unique([
                'school_id',
                'session_id',
                'term_id',
                'name',
            ], 'assessment_components_unique_per_context_no_subject');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        if ($this->hasIndex('assessment_components', 'assessment_components_unique_per_context_no_subject')) {
            Schema::table('assessment_components', function (Blueprint $table) {
                $table->dropUnique('assessment_components_unique_per_context_no_subject');
            });
        }

        Schema::table('assessment_components', function (Blueprint $table) {
            $table->uuid('subject_id')->nullable()->after('term_id');
        });

        $pivotRecords = DB::table('assessment_component_subject')->get();

        foreach ($pivotRecords as $record) {
            DB::table('assessment_components')
                ->where('id', $record->assessment_component_id)
                ->update(['subject_id' => $record->subject_id]);
        }

        Schema::table('assessment_components', function (Blueprint $table) {
            $table->foreign('subject_id')
                ->references('id')
                ->on('subjects')
                ->nullOnDelete();

            $table->unique([
                'school_id',
                'session_id',
                'term_id',
                'subject_id',
                'name',
            ], 'assessment_components_unique_per_context');
        });

        Schema::dropIfExists('assessment_component_subject');

        Schema::enableForeignKeyConstraints();
    }

    private function dropAssessmentComponentSubjectForeignKey(): void
    {
        if (! $this->tableHasColumn('assessment_components', 'subject_id')) {
            return;
        }

        $possibleNames = [
            'assessment_components_subject_id_foreign',
            'assessment_components_subject_id_foreign_key',
        ];

        foreach ($possibleNames as $name) {
            if ($this->hasForeignKey('assessment_components', $name)) {
                Schema::table('assessment_components', function (Blueprint $table) use ($name) {
                    $table->dropForeign($name);
                });
            }
        }

        try {
            Schema::table('assessment_components', function (Blueprint $table) {
                $table->dropIndex('assessment_components_subject_id_foreign');
            });
        } catch (\Throwable $e) {
            // Index name might not exist; ignore.
        }
    }

    private function hasForeignKey(string $table, string $foreignKey): bool
    {
        $schema = Schema::getConnection()->getDatabaseName();

        $result = Schema::getConnection()->selectOne('
            SELECT 1
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
            LIMIT 1
        ', [$schema, $table, $foreignKey]);

        return $result !== null;
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

    private function tableHasColumn(string $table, string $column): bool
    {
        $schema = Schema::getConnection()->getDatabaseName();

        $result = Schema::getConnection()->selectOne('
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = ?
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ', [$schema, $table, $column]);

        return $result !== null;
    }
};
