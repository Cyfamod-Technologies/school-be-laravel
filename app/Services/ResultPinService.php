<?php

namespace App\Services;

use App\Models\ResultPin;
use App\Models\Session;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResultPinService
{
    public function generateForStudent(Student $student, string $sessionId, string $termId, string $createdBy, array $options = []): ResultPin
    {
        $session = Session::query()
            ->where('school_id', $student->school_id)
            ->findOrFail($sessionId);

        $term = Term::query()
            ->where('school_id', $student->school_id)
            ->findOrFail($termId);

        if ($term->session_id !== $session->id) {
            throw ValidationException::withMessages([
                'term_id' => 'The selected term does not belong to the provided session.',
            ]);
        }

        $expiresAt = Arr::get($options, 'expires_at');
        if ($expiresAt) {
            $expiresAt = Carbon::parse($expiresAt);
        }

        $regenerate = (bool) Arr::get($options, 'regenerate', false);
        $hasMaxUsage = array_key_exists('max_usage', $options);
        $maxUsage = $hasMaxUsage ? Arr::get($options, 'max_usage') : null;
        if ($hasMaxUsage && $maxUsage !== null) {
            $maxUsage = (int) $maxUsage;
        }

        return DB::transaction(function () use ($student, $session, $term, $createdBy, $expiresAt, $regenerate, $hasMaxUsage, $maxUsage) {
            $existing = ResultPin::query()
                ->where('student_id', $student->id)
                ->where('session_id', $session->id)
                ->where('term_id', $term->id)
                ->first();

            $pinValue = $this->generateUniquePin();

            if ($existing) {
                if ($existing->status === 'active' && ! $regenerate) {
                    throw ValidationException::withMessages([
                        'pin' => 'A result PIN already exists for this student and term. Enable regenerate to replace it.',
                    ]);
                }

                $existing->pin_code = $pinValue;
                $existing->status = 'active';
                $existing->expires_at = $expiresAt ?? $existing->expires_at;
                $existing->revoked_at = null;
                $existing->created_by = $createdBy;
                $existing->use_count = 0;
                if ($hasMaxUsage) {
                    $existing->max_usage = $maxUsage;
                }
                $existing->save();

                return $existing->fresh();
            }

            return ResultPin::create([
                'student_id' => $student->id,
                'session_id' => $session->id,
                'term_id' => $term->id,
                'pin_code' => $pinValue,
                'status' => 'active',
                'expires_at' => $expiresAt,
                'created_by' => $createdBy,
                'use_count' => 0,
                'max_usage' => $hasMaxUsage ? $maxUsage : null,
            ]);
        });
    }

    public function invalidate(ResultPin $pin): ResultPin
    {
        if ($pin->status !== 'revoked') {
            $pin->status = 'revoked';
            $pin->revoked_at = Carbon::now();
            $pin->save();
        }

        return $pin;
    }

    protected function generateUniquePin(int $length = 10): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $alphabetLength = strlen($alphabet);

        do {
            $pin = '';
            for ($i = 0; $i < $length; $i++) {
                $pin .= $alphabet[random_int(0, $alphabetLength - 1)];
            }
        } while (ResultPin::query()->where('pin_code', $pin)->exists());

        return $pin;
    }
}
