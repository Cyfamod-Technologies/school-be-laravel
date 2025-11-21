<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if index already exists
        try {
            $indexExists = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                AND table_name = 'classes'
                AND index_name = 'classes_school_order_index'
            ");

            if ($indexExists[0]->count == 0) {
                Schema::table('classes', function (Blueprint $table) {
                    // Add composite index for school_id and order for efficient sorting
                    $table->index(['school_id', 'order'], 'classes_school_order_index');
                });
            }
        } catch (\Exception $e) {
            // Index might already exist or column doesn't exist, skip
        }

        // Update existing classes with proper order values using Laravel Eloquent
        // This is safer and more portable than raw SQL with user variables
        try {
            $schools = DB::table('classes')
                ->select('school_id')
                ->distinct()
                ->get();

            foreach ($schools as $school) {
                $classes = DB::table('classes')
                    ->where('school_id', $school->school_id)
                    ->orderByRaw("
                        CASE
                            WHEN name LIKE 'Nursery%' THEN 1
                            WHEN name LIKE 'Primary%' THEN 2
                            WHEN name LIKE 'JSS%' THEN 3
                            WHEN name LIKE 'SS%' THEN 4
                            ELSE 5
                        END
                    ")
                    ->orderByRaw("CAST(SUBSTRING_INDEX(name, ' ', -1) AS UNSIGNED)")
                    ->get();

                $order = 1;
                foreach ($classes as $class) {
                    DB::table('classes')
                        ->where('id', $class->id)
                        ->update(['order' => $order]);
                    $order++;
                }
            }
        } catch (\Exception $e) {
            // If update fails, it's okay - the seeder will set proper order values
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex('classes_school_order_index');
        });

        // Reset order values to 0
        DB::table('classes')->update(['order' => 0]);
    }
};
