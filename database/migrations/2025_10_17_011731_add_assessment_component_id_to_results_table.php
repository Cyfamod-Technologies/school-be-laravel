<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            if (! Schema::hasColumn('results', 'assessment_component_id')) {
                $table->uuid('assessment_component_id')->nullable()->after('subject_id');
                $table->foreign('assessment_component_id')
                    ->references('id')
                    ->on('assessment_components')
                    ->nullOnDelete();
                $table->index('assessment_component_id', 'results_assessment_component_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            if (Schema::hasColumn('results', 'assessment_component_id')) {
                $table->dropForeign(['assessment_component_id']);
                $table->dropIndex('results_assessment_component_id_index');
                $table->dropColumn('assessment_component_id');
            }
        });
    }
};
