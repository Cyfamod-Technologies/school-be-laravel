<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'skill_categories_separate_by_class')) {
                $table->boolean('skill_categories_separate_by_class')
                    ->default(false)
                    ->after('result_comment_mode');
            }

            if (! Schema::hasColumn('schools', 'skill_types_separate_by_class')) {
                $table->boolean('skill_types_separate_by_class')
                    ->default(false)
                    ->after('skill_categories_separate_by_class');
            }
        });

        Schema::table('skill_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('skill_categories', 'school_class_id')) {
                $table->uuid('school_class_id')
                    ->nullable()
                    ->after('school_id');
                $table->foreign('school_class_id')
                    ->references('id')
                    ->on('classes')
                    ->nullOnDelete();
                $table->index(['school_id', 'school_class_id'], 'skill_categories_school_class_index');
            }
        });

        Schema::table('skill_types', function (Blueprint $table) {
            if (! Schema::hasColumn('skill_types', 'school_class_id')) {
                $table->uuid('school_class_id')
                    ->nullable()
                    ->after('school_id');
                $table->foreign('school_class_id')
                    ->references('id')
                    ->on('classes')
                    ->nullOnDelete();
                $table->index(['school_id', 'school_class_id'], 'skill_types_school_class_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('skill_types', function (Blueprint $table) {
            if (Schema::hasColumn('skill_types', 'school_class_id')) {
                $table->dropIndex('skill_types_school_class_index');
                $table->dropForeign(['school_class_id']);
                $table->dropColumn('school_class_id');
            }
        });

        Schema::table('skill_categories', function (Blueprint $table) {
            if (Schema::hasColumn('skill_categories', 'school_class_id')) {
                $table->dropIndex('skill_categories_school_class_index');
                $table->dropForeign(['school_class_id']);
                $table->dropColumn('school_class_id');
            }
        });

        Schema::table('schools', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('schools', 'skill_categories_separate_by_class')) {
                $columns[] = 'skill_categories_separate_by_class';
            }

            if (Schema::hasColumn('schools', 'skill_types_separate_by_class')) {
                $columns[] = 'skill_types_separate_by_class';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
