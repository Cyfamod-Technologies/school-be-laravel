<?php

namespace App\Services\Fees;

use App\Models\FinanceSequence;
use App\Models\School;
use Illuminate\Support\Facades\DB;

/**
 * Mints payment references and receipt numbers.
 *
 * A counter row read with lockForUpdate beats `max(reference) + 1`, which
 * races the moment two parents submit evidence in the same second. Numbers are
 * per school, so two schools can both hold CYF-10001 without colliding.
 */
class FinanceSequenceGenerator
{
    private const FIRST_VALUE = 10001;

    public function paymentReference(School $school): string
    {
        return $this->format($school, FinanceSequence::KIND_PAYMENT_REFERENCE);
    }

    public function receiptNumber(School $school): string
    {
        return $this->format($school, FinanceSequence::KIND_RECEIPT_NUMBER);
    }

    private function format(School $school, string $kind): string
    {
        $number = $this->next($school->id, $kind);
        $prefix = $kind === FinanceSequence::KIND_RECEIPT_NUMBER ? 'RCT' : $this->prefixFor($school);

        return $prefix.'-'.$number;
    }

    /**
     * A school's own acronym reads better on a receipt than a generic prefix,
     * but only if it is short and alphabetic -- anything else falls back.
     */
    private function prefixFor(School $school): string
    {
        $acronym = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($school->acronym ?? '')));

        return $acronym !== '' && strlen($acronym) <= 6 ? $acronym : 'CYF';
    }

    private function next(string $schoolId, string $kind): int
    {
        return DB::transaction(function () use ($schoolId, $kind) {
            $sequence = FinanceSequence::query()
                ->where('school_id', $schoolId)
                ->where('kind', $kind)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                // firstOrCreate cannot be used here: two concurrent callers
                // would both miss and both insert, and only the unique index
                // would stop them. Let the loser of that race re-read instead.
                try {
                    $sequence = FinanceSequence::create([
                        'school_id' => $schoolId,
                        'kind' => $kind,
                        'next_value' => self::FIRST_VALUE,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    $sequence = FinanceSequence::query()
                        ->where('school_id', $schoolId)
                        ->where('kind', $kind)
                        ->lockForUpdate()
                        ->firstOrFail();
                }
            }

            $value = (int) $sequence->next_value;
            $sequence->update(['next_value' => $value + 1]);

            return $value;
        });
    }
}
