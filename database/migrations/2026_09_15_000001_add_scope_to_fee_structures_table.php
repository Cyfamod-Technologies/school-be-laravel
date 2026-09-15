<?php

use App\Models\FeeStructure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns `fee_structures` from a class-only table into the scope-aware
 * assignment table the fees module needs: a fee can now target the whole
 * school, one class, one class arm, or a hand-picked set of students.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The original unique index covers class_id. Once that column goes
        // nullable it stops protecting anything -- MariaDB treats every NULL
        // in a unique index as distinct, so every school-wide fee would look
        // unique to it. Drop it here and let `assignment_key` (added below)
        // do the work for all four scopes instead.
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropUnique('unique_fee_structure');
        });

        // Rename to match the rest of the codebase: students, class_arms and
        // subject assignments all call this column school_class_id.
        if (Schema::hasColumn('fee_structures', 'class_id') && ! Schema::hasColumn('fee_structures', 'school_class_id')) {
            Schema::table('fee_structures', function (Blueprint $table) {
                $table->renameColumn('class_id', 'school_class_id');
            });
        }

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->string('scope', 16)->default(FeeStructure::SCOPE_CLASS)->after('school_id');
            $table->uuid('school_class_id')->nullable()->change();
            $table->uuid('class_arm_id')->nullable()->after('school_class_id');
            $table->string('description')->nullable()->after('amount');
            $table->date('due_date')->nullable()->after('description');
            $table->boolean('is_active')->default(true)->after('is_mandatory');
            $table->uuid('created_by')->nullable()->after('is_active');

            // Nullable on purpose: only the school/class/class_arm scopes have
            // a natural key worth deduplicating. Two student-scoped assignments
            // of the same fee item in one term are legitimate (two excursions,
            // two sets of damages), so those rows carry NULL and MariaDB lets
            // any number of NULLs coexist in a unique index.
            $table->char('assignment_key', 64)->nullable()->after('created_by');
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->foreign('school_class_id')->references('id')->on('classes')->nullOnDelete();
            $table->foreign('class_arm_id')->references('id')->on('class_arms')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->unique('assignment_key');
            $table->index(['school_id', 'session_id', 'term_id'], 'fee_structures_school_period_index');
            $table->index(['school_id', 'scope'], 'fee_structures_school_scope_index');
        });

        // Everything that exists today was created through the class-only
        // endpoint, so it is a class-scoped assignment by definition.
        DB::table('fee_structures')->update(['scope' => FeeStructure::SCOPE_CLASS]);

        DB::table('fee_structures')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('fee_structures')
                    ->where('id', $row->id)
                    ->update([
                        'assignment_key' => FeeStructure::makeAssignmentKey(
                            $row->school_id,
                            FeeStructure::SCOPE_CLASS,
                            $row->school_class_id,
                            null,
                            $row->session_id,
                            $row->term_id,
                            $row->fee_item_id,
                        ),
                    ]);
            }
        });
    }

    public function down(): void
    {
        // Both indexes added by up() start with school_id, so InnoDB counts
        // them as support for the school_id foreign key and refuses to drop
        // the last one while that key exists. Drop the key first and re-add it
        // at the end, which lets InnoDB rebuild its own supporting index.
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropForeign(['school_class_id']);
            $table->dropForeign(['class_arm_id']);
            $table->dropForeign(['created_by']);
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropUnique(['assignment_key']);
            $table->dropIndex('fee_structures_school_period_index');
            $table->dropIndex('fee_structures_school_scope_index');
            $table->dropColumn([
                'scope',
                'class_arm_id',
                'description',
                'due_date',
                'is_active',
                'created_by',
                'assignment_key',
            ]);
        });

        // Rows that were never class-scoped have no home in the old shape.
        DB::table('fee_structures')->whereNull('school_class_id')->delete();

        if (Schema::hasColumn('fee_structures', 'school_class_id')) {
            Schema::table('fee_structures', function (Blueprint $table) {
                $table->renameColumn('school_class_id', 'class_id');
            });
        }

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->uuid('class_id')->nullable(false)->change();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->cascadeOnDelete();
            $table->unique(['class_id', 'session_id', 'term_id', 'fee_item_id'], 'unique_fee_structure');
        });
    }
};
