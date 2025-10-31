<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'acronym')) {
                $table->string('acronym', 20)->nullable()->after('name');
            }

            if (! Schema::hasColumn('schools', 'code_sequence')) {
                $table->unsignedInteger('code_sequence')->nullable()->after('acronym');
            }

            if (! $this->indexExists('schools', 'schools_code_sequence_unique')) {
                $table->unique('code_sequence', 'schools_code_sequence_unique');
            }
        });

        DB::transaction(function () {
            $existingMax = (int) DB::table('schools')
                ->whereNotNull('code_sequence')
                ->max('code_sequence');

            $nextSequence = $existingMax > 0 ? $existingMax + 1 : 1;

            $schools = DB::table('schools')
                ->select('id', 'name', 'acronym', 'code_sequence')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            foreach ($schools as $school) {
                $acronym = $school->acronym ?? $this->deriveAcronym($school->name);
                $codeSequence = $school->code_sequence;

                if ($codeSequence === null) {
                    $codeSequence = $nextSequence;
                    $nextSequence++;
                }

                DB::table('schools')
                    ->where('id', $school->id)
                    ->update([
                        'acronym' => $acronym,
                        'code_sequence' => $codeSequence,
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if ($this->indexExists('schools', 'schools_code_sequence_unique')) {
                $table->dropUnique('schools_code_sequence_unique');
            }

            if (Schema::hasColumn('schools', 'code_sequence')) {
                $table->dropColumn('code_sequence');
            }

            if (Schema::hasColumn('schools', 'acronym')) {
                $table->dropColumn('acronym');
            }
        });
    }

    private function deriveAcronym(?string $name): string
    {
        $fallback = 'SCH';

        if (! $name) {
            return $fallback;
        }

        $words = collect(preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY));

        $acronym = $words
            ->map(fn ($word) => mb_substr($word, 0, 1))
            ->implode('');

        $acronym = Str::upper(Str::of($acronym)->replaceMatches('/[^A-Z]/', ''));

        if ($acronym === '') {
            $acronym = Str::upper(mb_substr($name, 0, 3));
        }

        return Str::limit($acronym, 5, '');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $databaseName = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(*) AS count FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$databaseName, $table, $indexName]
        );

        return (int) ($result->count ?? 0) > 0;
    }
};
