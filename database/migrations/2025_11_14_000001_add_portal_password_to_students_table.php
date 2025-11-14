<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'portal_password')) {
                $table->string('portal_password')->nullable()->after('medical_information');
            }
            if (! Schema::hasColumn('students', 'portal_password_changed_at')) {
                $table->timestamp('portal_password_changed_at')->nullable()->after('portal_password');
            }
        });

        if (Schema::hasColumn('students', 'portal_password')) {
            DB::table('students')
                ->whereNull('portal_password')
                ->update([
                    'portal_password' => Hash::make('123456'),
                    'portal_password_changed_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'portal_password_changed_at')) {
                $table->dropColumn('portal_password_changed_at');
            }
            if (Schema::hasColumn('students', 'portal_password')) {
                $table->dropColumn('portal_password');
            }
        });
    }
};
