<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('class_teachers')) {
            return;
        }

        if (Schema::hasColumn('class_teachers', 'class_section_id')) {
            Schema::table('class_teachers', function (Blueprint $table) {
                $table->dropForeign(['class_section_id']);
            });

            Schema::table('class_teachers', function (Blueprint $table) {
                $table->dropColumn('class_section_id');
            });
        }

        Schema::table('class_teachers', function (Blueprint $table) {
            if (! Schema::hasColumn('class_teachers', 'school_class_id')) {
                $table->uuid('school_class_id')->after('staff_id');
            }

            if (! Schema::hasColumn('class_teachers', 'class_arm_id')) {
                $table->uuid('class_arm_id')->after('school_class_id');
            }

            if (! Schema::hasColumn('class_teachers', 'class_section_id')) {
                $table->uuid('class_section_id')->nullable()->after('class_arm_id');
            }
        });

        Schema::table('class_teachers', function (Blueprint $table) {
            if (Schema::hasColumn('class_teachers', 'school_class_id')) {
                $table->foreign('school_class_id')
                    ->references('id')
                    ->on('classes')
                    ->cascadeOnDelete();
            }

            if (Schema::hasColumn('class_teachers', 'class_arm_id')) {
                $table->foreign('class_arm_id')
                    ->references('id')
                    ->on('class_arms')
                    ->cascadeOnDelete();
            }

            if (Schema::hasColumn('class_teachers', 'class_section_id')) {
                $table->foreign('class_section_id')
                    ->references('id')
                    ->on('class_sections')
                    ->nullOnDelete();
            }
        });

        // Ensure unique per class arm + term + session (ignores section)
        Schema::table('class_teachers', function (Blueprint $table) {
            if (! $this->hasIndex('class_teachers', 'class_teachers_arm_session_term_unique')) {
                $table->unique(['class_arm_id', 'class_section_id', 'session_id', 'term_id'], 'class_teachers_arm_session_term_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('class_teachers')) {
            return;
        }

        Schema::table('class_teachers', function (Blueprint $table) {
            if (Schema::hasColumn('class_teachers', 'class_section_id')) {
                $table->dropForeign(['class_section_id']);
            }
            if (Schema::hasColumn('class_teachers', 'class_arm_id')) {
                $table->dropForeign(['class_arm_id']);
            }
            if (Schema::hasColumn('class_teachers', 'school_class_id')) {
                $table->dropForeign(['school_class_id']);
            }
            if ($this->hasIndex('class_teachers', 'class_teachers_arm_session_term_unique')) {
                $table->dropUnique('class_teachers_arm_session_term_unique');
            }
        });

        Schema::table('class_teachers', function (Blueprint $table) {
            if (Schema::hasColumn('class_teachers', 'class_section_id')) {
                $table->dropColumn('class_section_id');
            }
            if (Schema::hasColumn('class_teachers', 'class_arm_id')) {
                $table->dropColumn('class_arm_id');
            }
            if (Schema::hasColumn('class_teachers', 'school_class_id')) {
                $table->dropColumn('school_class_id');
            }
        });

        Schema::table('class_teachers', function (Blueprint $table) {
            if (! Schema::hasColumn('class_teachers', 'class_section_id')) {
                $table->uuid('class_section_id')->after('staff_id');
            }
        });

        Schema::table('class_teachers', function (Blueprint $table) {
            if (Schema::hasColumn('class_teachers', 'class_section_id')) {
                $table->foreign('class_section_id')
                    ->references('id')
                    ->on('class_sections')
                    ->cascadeOnDelete();
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::selectOne('
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = ?
              AND table_name = ?
              AND index_name = ?
            LIMIT 1
        ', [$database, $table, $index]);

        return $result !== null;
    }
};
