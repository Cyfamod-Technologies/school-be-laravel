<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\Term;
use App\Services\Fees\BillGenerationService;
use Illuminate\Console\Command;

/**
 * Backfill and repair for student bills.
 *
 * Generation is idempotent, so this is always safe to re-run: it is the tool
 * for rolling the module out to an existing school, and for putting things
 * right if an assignment was changed while the queue was down.
 */
class GenerateStudentBills extends Command
{
    protected $signature = 'fees:generate-bills
                            {--school= : School ID (defaults to every school)}
                            {--session= : Session ID (defaults to the school\'s current session)}
                            {--term= : Term ID (defaults to the school\'s current term)}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Generate or refresh student bills from the fee assignments for a session and term';

    public function handle(BillGenerationService $bills): int
    {
        $schools = $this->resolveSchools();

        if ($schools->isEmpty()) {
            $this->warn('No matching schools found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $failed = false;

        foreach ($schools as $school) {
            $sessionId = $this->option('session') ?: $school->current_session_id;
            $termId = $this->option('term') ?: $school->current_term_id;

            if (! $sessionId || ! $termId) {
                $this->warn("Skipping {$school->name}: no session/term given and none set as current.");
                $failed = true;

                continue;
            }

            if (! $this->periodBelongsToSchool($school->id, $sessionId, $termId)) {
                $this->warn("Skipping {$school->name}: the given session/term does not belong to it.");
                $failed = true;

                continue;
            }

            $summary = $dryRun
                ? $this->preview($school->id, $sessionId, $termId)
                : $bills->generateForSchool($school->id, $sessionId, $termId);

            $rows[] = [
                $school->name,
                $summary['students'],
                $summary['items_created'],
                $summary['items_updated'],
                $summary['items_removed'],
            ];
        }

        if ($rows !== []) {
            $this->table(
                ['School', 'Students', 'Created', 'Updated', 'Removed'],
                $rows,
            );
        }

        if ($dryRun) {
            $this->info('Dry run: nothing was written.');
        }

        // A skipped school is a misconfiguration the operator needs to see in
        // the exit code, not just in the scrollback.
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, School>
     */
    private function resolveSchools()
    {
        $schoolId = $this->option('school');

        return School::query()
            ->when($schoolId, fn ($query) => $query->whereKey($schoolId))
            ->get(['id', 'name', 'current_session_id', 'current_term_id']);
    }

    private function periodBelongsToSchool(string $schoolId, string $sessionId, string $termId): bool
    {
        return Term::query()
            ->whereKey($termId)
            ->where('school_id', $schoolId)
            ->where('session_id', $sessionId)
            ->exists();
    }

    /**
     * @return array{students: int, items_created: int, items_updated: int, items_removed: int}
     */
    private function preview(string $schoolId, string $sessionId, string $termId): array
    {
        $assignments = \App\Models\FeeStructure::query()
            ->where('school_id', $schoolId)
            ->where('session_id', $sessionId)
            ->where('term_id', $termId)
            ->where('is_active', true)
            ->count();

        $students = \App\Models\Student::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->count();

        $this->line("  {$students} billable student(s), {$assignments} active assignment(s).");

        return [
            'students' => $students,
            'items_created' => 0,
            'items_updated' => 0,
            'items_removed' => 0,
        ];
    }
}
