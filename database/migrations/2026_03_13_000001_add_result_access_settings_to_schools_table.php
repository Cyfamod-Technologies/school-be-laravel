<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'result_hide_student_identity')) {
                $table->boolean('result_hide_student_identity')
                    ->default(false)
                    ->after('result_show_remarks');
            }

            if (! Schema::hasColumn('schools', 'result_allow_shared_pin_access')) {
                $table->boolean('result_allow_shared_pin_access')
                    ->default(false)
                    ->after('result_hide_student_identity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('schools', 'result_hide_student_identity')) {
                $columns[] = 'result_hide_student_identity';
            }

            if (Schema::hasColumn('schools', 'result_allow_shared_pin_access')) {
                $columns[] = 'result_allow_shared_pin_access';
            }

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
