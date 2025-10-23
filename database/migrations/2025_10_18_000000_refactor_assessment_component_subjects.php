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
                $table->foreign('assessment_component_id', 'assessment_component_subject_component_fk')->references('id')->on('assessment_components')->cascadeOnDelete();
                $table->foreign('subject_id', 'assessment_component_subject_subject_fk')->references('id')->on('subjects')->cascadeOnDelete();
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
                $key = implode('|', [$component->school_id, $component->session_id, $component->term_id, $component->name, number_format((float) $component->weight, 2, '.', ''), (string) $component->order, (string) ($component->label ?? '')]);
                $groups[$key][] = $component;
            }

            foreach ($groups as $groupComponents) {
                $primary = $groupComponents[0];
                $handledSubjectIds = [];
                foreach ($groupComponents as $component) {
                    if (! empty($component->subject_id) && ! in_array($component->subject_id, $handledSubjectIds, true)) {
                        DB::table('assessment_component_subject')->insertOrIgnore(['assessment_component_id' => $primary->id, 'subject_id' => $component->subject_id]);
                        $handledSubjectIds[] = $component->subject_id;
                    }
                    if ($component->id !== $primary->id) {
                        DB::table('results')->where('assessment_component_id', $component->id)->update(['assessment_component_id' => $primary->id]);
                    }
                }
                $duplicateIds = array_map(fn($c) => $c->id, array_filter($groupComponents, fn($c) => $c->id !== $primary->id));
                if (! empty($duplicateIds)) {
                    DB::table('assessment_components')->whereIn('id', $duplicateIds)->delete();
                }
            }
        }

        // Drop all dependent foreign keys first
        Schema::table('results', function (Blueprint $table) {
            $table->dropForeign(['assessment_component_id']);
        });
        
        $this->dropAssessmentComponentSubjectForeignKey();
        
        // Find and drop any foreign keys that reference assessment_components table
        $this->dropAllForeignKeysReferencingAssessmentComponents();
        
        // Now it's safe to drop the index
        Schema::table('assessment_components', function (Blueprint $table) {
            if ($this->hasIndex('assessment_components', 'assessment_components_unique_per_context')) {
                $table->dropUnique('assessment_components_unique_per_context');
            }
        });

        if ($hasSubjectColumn) {
            if (DB::getDriverName() === 'sqlite') {
                Schema::create('assessment_components_new', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->uuid('school_id');
                    $table->uuid('session_id');
                    $table->uuid('term_id');
                    $table->string('name');
                    $table->decimal('weight', 5, 2);
                    $table->integer('order');
                    $table->string('label')->nullable();
                    $table->timestamps();
                });
                DB::statement('INSERT INTO assessment_components_new SELECT id, school_id, session_id, term_id, name, weight, "order", label, created_at, updated_at FROM assessment_components');
                Schema::drop('assessment_components');
                Schema::rename('assessment_components_new', 'assessment_components');
            } else {
                Schema::table('assessment_components', function (Blueprint $table) {
                    $table->dropColumn('subject_id');
                });
            }
        }

        Schema::table('assessment_components', function (Blueprint $table) {
            $table->unique(['school_id', 'session_id', 'term_id', 'name'], 'assessment_components_unique_per_context_no_subject');
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
            DB::table('assessment_components')->where('id', $record->assessment_component_id)->update(['subject_id' => $record->subject_id]);
        }

        Schema::table('assessment_components', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
            $table->unique(['school_id', 'session_id', 'term_id', 'subject_id', 'name'], 'assessment_components_unique_per_context');
        });

        Schema::dropIfExists('assessment_component_subject');
        
        Schema::enableForeignKeyConstraints();
    }

    private function dropAssessmentComponentSubjectForeignKey(): void
    {
        if (! $this->tableHasColumn('assessment_components', 'subject_id')) return;
        $possibleNames = ['assessment_components_subject_id_foreign', 'assessment_components_subject_id_foreign_key'];
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
        } catch (\Throwable $e) {}
    }

    private function hasForeignKey(string $table, string $foreignKey): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $keys = DB::select("PRAGMA foreign_key_list({$table})");
            foreach ($keys as $key) {
                if ($key->id === $foreignKey) {
                    return true;
                }
            }
            return false;
        }

        $schema = Schema::getConnection()->getDatabaseName();
        $result = Schema::getConnection()->selectOne("SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? LIMIT 1", [$schema, $table, $foreignKey]);
        return $result !== null;
    }

    private function hasIndex(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list({$table})");
            foreach ($indexes as $indexInfo) {
                if ($indexInfo->name === $index) {
                    return true;
                }
            }
            return false;
        }

        $schema = Schema::getConnection()->getDatabaseName();
        $result = Schema::getConnection()->selectOne("SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1", [$schema, $table, $index]);
        return $result !== null;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }
    
    private function dropAllForeignKeysReferencingAssessmentComponents(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, we need to check each table manually
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            foreach ($tables as $table) {
                $foreignKeys = DB::select("PRAGMA foreign_key_list({$table->name})");
                foreach ($foreignKeys as $foreignKey) {
                    if ($foreignKey->table === 'assessment_components') {
                        // For SQLite, we need to recreate the table without the foreign key
                        // This is a simplified approach - in a real scenario, you might need more complex logic
                        try {
                            DB::statement("DROP TABLE IF EXISTS {$table->name}_temp");
                            DB::statement("CREATE TABLE {$table->name}_temp AS SELECT * FROM {$table->name}");
                            DB::statement("DROP TABLE {$table->name}");
                            DB::statement("ALTER TABLE {$table->name}_temp RENAME TO {$table->name}");
                        } catch (\Throwable $e) {
                            // Log or handle error
                        }
                    }
                }
            }
        } else {
            // For MySQL/PostgreSQL, we can query the information schema
            $schema = Schema::getConnection()->getDatabaseName();
            $constraints = DB::select("
                SELECT 
                    TABLE_NAME, 
                    CONSTRAINT_NAME 
                FROM 
                    information_schema.KEY_COLUMN_USAGE 
                WHERE 
                    REFERENCED_TABLE_SCHEMA = ? AND 
                    REFERENCED_TABLE_NAME = ?
            ", [$schema, 'assessment_components']);
            
            foreach ($constraints as $constraint) {
                try {
                    Schema::table($constraint->TABLE_NAME, function (Blueprint $table) use ($constraint) {
                        $table->dropForeign($constraint->CONSTRAINT_NAME);
                    });
                } catch (\Throwable $e) {
                    // Log or handle error
                }
            }
        }
    }
};
