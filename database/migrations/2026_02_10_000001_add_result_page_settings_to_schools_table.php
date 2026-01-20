<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'result_show_grade')) {
                $table->boolean('result_show_grade')->default(true)->after('current_term_id');
            }
            if (! Schema::hasColumn('schools', 'result_show_position')) {
                $table->boolean('result_show_position')->default(true)->after('result_show_grade');
            }
            if (! Schema::hasColumn('schools', 'result_show_class_average')) {
                $table->boolean('result_show_class_average')->default(true)->after('result_show_position');
            }
            if (! Schema::hasColumn('schools', 'result_show_lowest')) {
                $table->boolean('result_show_lowest')->default(true)->after('result_show_class_average');
            }
            if (! Schema::hasColumn('schools', 'result_show_highest')) {
                $table->boolean('result_show_highest')->default(true)->after('result_show_lowest');
            }
            if (! Schema::hasColumn('schools', 'result_show_remarks')) {
                $table->boolean('result_show_remarks')->default(true)->after('result_show_highest');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('schools', 'result_show_grade')) {
                $columns[] = 'result_show_grade';
            }
            if (Schema::hasColumn('schools', 'result_show_position')) {
                $columns[] = 'result_show_position';
            }
            if (Schema::hasColumn('schools', 'result_show_class_average')) {
                $columns[] = 'result_show_class_average';
            }
            if (Schema::hasColumn('schools', 'result_show_lowest')) {
                $columns[] = 'result_show_lowest';
            }
            if (Schema::hasColumn('schools', 'result_show_highest')) {
                $columns[] = 'result_show_highest';
            }
            if (Schema::hasColumn('schools', 'result_show_remarks')) {
                $columns[] = 'result_show_remarks';
            }

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
