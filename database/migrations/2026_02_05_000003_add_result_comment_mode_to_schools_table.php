<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('schools', 'result_comment_mode')) {
            return;
        }

        $afterColumn = null;
        if (Schema::hasColumn('schools', 'result_show_remarks')) {
            $afterColumn = 'result_show_remarks';
        } elseif (Schema::hasColumn('schools', 'result_show_highest')) {
            $afterColumn = 'result_show_highest';
        } elseif (Schema::hasColumn('schools', 'current_term_id')) {
            $afterColumn = 'current_term_id';
        }

        Schema::table('schools', function (Blueprint $table) use ($afterColumn) {
            $column = $table->string('result_comment_mode', 20)->default('manual');

            if ($afterColumn !== null) {
                $column->after($afterColumn);
            }
        });
    }

    public function down()
    {
        if (Schema::hasColumn('schools', 'result_comment_mode')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropColumn('result_comment_mode');
            });
        }
    }
};
