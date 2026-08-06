<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subject_school_class_assignments')) {
            $this->syncColumnDefinition('class_arms', 'id', 'subject_school_class_assignments', 'class_arm_id', true);
            $this->syncColumnDefinition('class_sections', 'id', 'subject_school_class_assignments', 'class_section_id', true);

            Schema::table('subject_school_class_assignments', function (Blueprint $table) {
                if (! Schema::hasColumn('subject_school_class_assignments', 'class_arm_id')) {
                    $table->uuid('class_arm_id')->nullable()->after('school_class_id');
                }

                if (! Schema::hasColumn('subject_school_class_assignments', 'class_section_id')) {
                    $table->uuid('class_section_id')->nullable()->after('class_arm_id');
                }
            });

            Schema::table('subject_school_class_assignments', function (Blueprint $table) {
                if (! $this->hasForeignKey('subject_school_class_assignments', 'subject_school_class_assignments_class_arm_id_foreign')) {
                    $table->foreign('class_arm_id')
                        ->references('id')
                        ->on('class_arms')
                        ->nullOnDelete();
                }

                if (! $this->hasForeignKey('subject_school_class_assignments', 'subject_school_class_assignments_class_section_id_foreign')) {
                    $table->foreign('class_section_id')
                        ->references('id')
                        ->on('class_sections')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('subject_teacher_assignments')) {
            // Ensure engine is InnoDB for FK support
            DB::statement('ALTER TABLE subject_teacher_assignments ENGINE=InnoDB');
            DB::statement('ALTER TABLE class_sections ENGINE=InnoDB');

            Schema::table('subject_teacher_assignments', function (Blueprint $table) {
                if (! Schema::hasColumn('subject_teacher_assignments', 'school_class_id')) {
                    $table->uuid('school_class_id')->nullable()->after('subject_id');
                }

                if (! Schema::hasColumn('subject_teacher_assignments', 'class_arm_id')) {
                    $table->uuid('class_arm_id')->nullable()->after('school_class_id');
                }
            });

            if (Schema::hasColumn('subject_teacher_assignments', 'class_section_id')) {
                if ($this->hasForeignKey('subject_teacher_assignments', 'subject_teacher_assignments_class_section_id_foreign')) {
                    Schema::table('subject_teacher_assignments', function (Blueprint $table) {
                        $table->dropForeign('subject_teacher_assignments_class_section_id_foreign');
                    });
                }

                // Normalize table charsets/collations to avoid FK mismatch
                DB::statement('ALTER TABLE class_sections CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                DB::statement('ALTER TABLE subject_teacher_assignments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

                // Do not alter class_sections.id to avoid breaking existing FKs; assume it is CHAR(36)

                DB::statement("
                    UPDATE subject_teacher_assignments
                    SET class_section_id = NULL
                    WHERE class_section_id IS NULL
                       OR TRIM(class_section_id) = ''
                       OR TRIM(class_section_id) = '0'
                       OR CHAR_LENGTH(class_section_id) <> 36
                ");

                // Ensure referencing column exactly matches
                DB::statement(
                    'ALTER TABLE subject_teacher_assignments MODIFY class_section_id CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL'
                );

                DB::statement('
                    UPDATE subject_teacher_assignments sta
                    LEFT JOIN class_sections cs ON cs.id = sta.class_section_id
                    SET sta.class_section_id = NULL
                    WHERE cs.id IS NULL
                ');

                DB::statement('
                    UPDATE subject_teacher_assignments sta
                    SET sta.class_section_id = NULL
                    WHERE sta.class_section_id IS NOT NULL
                      AND NOT EXISTS (
                          SELECT 1 FROM class_sections cs
                          WHERE cs.id = sta.class_section_id
                      )
                ');
                $this->syncColumnDefinition('class_sections', 'id', 'subject_teacher_assignments', 'class_section_id', true);
            }

            $this->syncColumnDefinition('classes', 'id', 'subject_teacher_assignments', 'school_class_id', true);
            $this->syncColumnDefinition('class_arms', 'id', 'subject_teacher_assignments', 'class_arm_id', true);

            Schema::table('subject_teacher_assignments', function (Blueprint $table) {
                if (! $this->hasForeignKey('subject_teacher_assignments', 'subject_teacher_assignments_school_class_id_foreign')) {
                    $table->foreign('school_class_id')
                        ->references('id')
                        ->on('classes')
                        ->nullOnDelete();
                }

                if (! $this->hasForeignKey('subject_teacher_assignments', 'subject_teacher_assignments_class_arm_id_foreign')) {
                    $table->foreign('class_arm_id')
                        ->references('id')
                        ->on('class_arms')
                        ->nullOnDelete();
                }

                // Ensure index exists before creating foreign key (required in some MariaDB versions)
                if (! $this->hasIndex('subject_teacher_assignments', 'subject_teacher_assignments_class_section_id_index')) {
                    $table->index('class_section_id', 'subject_teacher_assignments_class_section_id_index');
                }

                if (! $this->hasForeignKey('subject_teacher_assignments', 'subject_teacher_assignments_class_section_id_foreign')) {
                    $table->foreign('class_section_id')
                        ->references('id')
                        ->on('class_sections')
                        ->nullOnDelete();
                }
            });

            $this->hydrateTeacherAssignments();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subject_school_class_assignments')) {
            Schema::table('subject_school_class_assignments', function (Blueprint $table) {
                if ($this->hasForeignKey('subject_school_class_assignments', 'subject_school_class_assignments_class_section_id_foreign')) {
                    $table->dropForeign('subject_school_class_assignments_class_section_id_foreign');
                }

                if ($this->hasForeignKey('subject_school_class_assignments', 'subject_school_class_assignments_class_arm_id_foreign')) {
                    $table->dropForeign('subject_school_class_assignments_class_arm_id_foreign');
                }

                if (Schema::hasColumn('subject_school_class_assignments', 'class_section_id')) {
                    $table->dropColumn('class_section_id');
                }

                if (Schema::hasColumn('subject_school_class_assignments', 'class_arm_id')) {
                    $table->dropColumn('class_arm_id');
                }
            });
        }

        if (Schema::hasTable('subject_teacher_assignments')) {
            Schema::table('subject_teacher_assignments', function (Blueprint $table) {
                if ($this->hasForeignKey('subject_teacher_assignments', 'subject_teacher_assignments_class_section_id_foreign')) {
                    $table->dropForeign('subject_teacher_assignments_class_section_id_foreign');
                }

                if ($this->hasForeignKey('subject_teacher_assignments', 'subject_teacher_assignments_class_arm_id_foreign')) {
                    $table->dropForeign('subject_teacher_assignments_class_arm_id_foreign');
                }

                if ($this->hasForeignKey('subject_teacher_assignments', 'subject_teacher_assignments_school_class_id_foreign')) {
                    $table->dropForeign('subject_teacher_assignments_school_class_id_foreign');
                }

                if (Schema::hasColumn('subject_teacher_assignments', 'class_arm_id')) {
                    $table->dropColumn('class_arm_id');
                }

                if (Schema::hasColumn('subject_teacher_assignments', 'school_class_id')) {
                    $table->dropColumn('school_class_id');
                }
            });

            if (Schema::hasColumn('subject_teacher_assignments', 'class_section_id')) {
                DB::statement('DELETE FROM subject_teacher_assignments WHERE class_section_id IS NULL');

                $this->syncColumnDefinition('class_sections', 'id', 'subject_teacher_assignments', 'class_section_id', false);

                Schema::table('subject_teacher_assignments', function (Blueprint $table) {
                    if (! $this->hasForeignKey('subject_teacher_assignments', 'subject_teacher_assignments_class_section_id_foreign')) {
                        $table->foreign('class_section_id')
                            ->references('id')
                            ->on('class_sections')
                            ->onDelete('cascade');
                    }
                });
            }
        }
    }

    private function hydrateTeacherAssignments(): void
    {
        if (! Schema::hasTable('subject_teacher_assignments')) {
            return;
        }

        if (! Schema::hasTable('class_sections') || ! Schema::hasTable('class_arms')) {
            return;
        }

        $armColumn = Schema::hasColumn('class_sections', 'class_arm_id') ? 'class_arm_id' : null;
        $classColumn = Schema::hasColumn('class_arms', 'school_class_id')
            ? 'school_class_id'
            : (Schema::hasColumn('class_arms', 'class_id') ? 'class_id' : null);

        if (! $armColumn || ! $classColumn) {
            return;
        }

        DB::statement("
            UPDATE subject_teacher_assignments sta
            LEFT JOIN class_sections cs ON cs.id = sta.class_section_id
            LEFT JOIN class_arms ca ON ca.id = cs.{$armColumn}
            SET
                sta.class_arm_id = COALESCE(sta.class_arm_id, cs.{$armColumn}),
                sta.school_class_id = COALESCE(sta.school_class_id, ca.{$classColumn})
            WHERE sta.class_section_id IS NOT NULL
        ");
    }

    private function hasForeignKey(string $table, string $foreignKey): bool
    {
        $databaseName = DB::getDatabaseName();

        $result = DB::selectOne('
            SELECT CONSTRAINT_NAME
            FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = ?
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
        ', [$databaseName, $table, $foreignKey]);

        return $result !== null;
    }

    private function syncColumnDefinition(string $sourceTable, string $sourceColumn, string $targetTable, string $targetColumn, bool $nullable): void
    {
        if (! Schema::hasTable($sourceTable) || ! Schema::hasTable($targetTable)) {
            return;
        }

        if (! Schema::hasColumn($sourceTable, $sourceColumn) || ! Schema::hasColumn($targetTable, $targetColumn)) {
            return;
        }

        $databaseName = DB::getDatabaseName();

        $column = DB::selectOne('
            SELECT COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ', [$databaseName, $sourceTable, $sourceColumn]);

        if (! $column || ! $column->COLUMN_TYPE) {
            return;
        }

        $type = strtoupper($column->COLUMN_TYPE);
        $charset = $column->CHARACTER_SET_NAME ? ' CHARACTER SET '.$column->CHARACTER_SET_NAME : '';
        $collation = $column->COLLATION_NAME ? ' COLLATE '.$column->COLLATION_NAME : '';
        $nullability = $nullable ? ' NULL' : ' NOT NULL';

        $sql = sprintf(
            'ALTER TABLE `%s` MODIFY `%s` %s%s%s%s',
            $targetTable,
            $targetColumn,
            $type,
            $charset,
            $collation,
            $nullability
        );

        DB::statement($sql);
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $databaseName = DB::getDatabaseName();

        $result = DB::selectOne('
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
        ', [$databaseName, $table, $indexName]);

        return $result !== null;
    }
};
