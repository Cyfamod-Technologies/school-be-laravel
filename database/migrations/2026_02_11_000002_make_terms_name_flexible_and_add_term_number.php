<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->convertNameColumnToString();

        if (! Schema::hasColumn('terms', 'term_number')) {
            Schema::table('terms', function (Blueprint $table) {
                $table->unsignedTinyInteger('term_number')->nullable()->after('name');
            });
        }

        $this->backfillTermNumbers();

        Schema::table('terms', function (Blueprint $table) {
            $table->unsignedTinyInteger('term_number')->nullable(false)->change();
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->unique(['session_id', 'term_number'], 'terms_session_term_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('terms', 'term_number')) {
            DB::table('terms')
                ->where('term_number', 1)
                ->update(['name' => '1st']);
            DB::table('terms')
                ->where('term_number', 2)
                ->update(['name' => '2nd']);
            DB::table('terms')
                ->whereNotIn('term_number', [1, 2])
                ->update(['name' => '3rd']);

            Schema::table('terms', function (Blueprint $table) {
                $table->dropUnique('terms_session_term_number_unique');
            });

            Schema::table('terms', function (Blueprint $table) {
                $table->dropColumn('term_number');
            });
        }

        $this->convertNameColumnToEnum();
    }

    private function convertNameColumnToString(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE terms MODIFY COLUMN name VARCHAR(100) NOT NULL');
            return;
        }

        Schema::table('terms', function (Blueprint $table) {
            $table->string('name', 100)->change();
        });
    }

    private function convertNameColumnToEnum(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE terms MODIFY COLUMN name ENUM('1st','2nd','3rd') NOT NULL");
            return;
        }

        Schema::table('terms', function (Blueprint $table) {
            $table->enum('name', ['1st', '2nd', '3rd'])->change();
        });
    }

    private function backfillTermNumbers(): void
    {
        $terms = DB::table('terms')
            ->select(['id', 'session_id', 'name', 'start_date'])
            ->orderBy('session_id')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $usedBySession = [];

        foreach ($terms as $term) {
            $sessionId = (string) $term->session_id;
            $used = $usedBySession[$sessionId] ?? [];

            $inferred = $this->inferTermNumber((string) $term->name);
            $termNumber = null;

            if ($inferred !== null && ! in_array($inferred, $used, true)) {
                $termNumber = $inferred;
            }

            if ($termNumber === null) {
                $candidate = 1;
                while (in_array($candidate, $used, true)) {
                    $candidate++;
                }
                $termNumber = $candidate;
            }

            DB::table('terms')
                ->where('id', $term->id)
                ->update(['term_number' => $termNumber]);

            $used[] = $termNumber;
            $usedBySession[$sessionId] = $used;
        }
    }

    private function inferTermNumber(string $name): ?int
    {
        $normalized = Str::of($name)
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/\b(1st|first)\b/', $normalized) === 1) {
            return 1;
        }

        if (preg_match('/\b(2nd|second)\b/', $normalized) === 1) {
            return 2;
        }

        if (preg_match('/\b(3rd|third)\b/', $normalized) === 1) {
            return 3;
        }

        return null;
    }
};
