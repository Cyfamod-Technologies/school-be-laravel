<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'blood_group_id')) {
                $table->uuid('blood_group_id')->nullable()->after('lga_of_origin');
                $table->foreign('blood_group_id')
                    ->references('id')
                    ->on('blood_groups')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'blood_group_id')) {
                $table->dropForeign(['blood_group_id']);
                $table->dropColumn('blood_group_id');
            }
        });
    }
};

