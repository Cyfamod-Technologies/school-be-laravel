<?php

namespace App\Services\Fees;

use App\Models\BankDetail;
use App\Models\Payment;
use App\Models\PaymentEvidence;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Recording a payment -- either a student/parent submitting evidence of an
 * offline transfer, or an admin entering one they took at the desk.
 *
 * Both land as rows in `payments`. Neither is money yet: a submission starts
 * at STATUS_PENDING and an admin-entered one still needs verifying unless the
 * caller explicitly says otherwise.
 */
class PaymentSubmissionService
{
    /** Evidence we will accept and can actually display back to an admin. */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    public const MAX_EVIDENCE_BYTES = 5 * 1024 * 1024;

    public const EVIDENCE_DISK = 'evidence';

    public function __construct(
        private readonly FinanceSequenceGenerator $sequences,
        private readonly FinanceAuditLogger $audit,
    ) {}

    /**
     * A student or parent submitting proof of a transfer they have already made.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function submit(Student $student, array $data, array $files, Model $actor): Payment
    {
        $school = $student->school;

        if (! $school) {
            throw ValidationException::withMessages([
                'student' => ['This student is not attached to a school.'],
            ]);
        }

        $this->assertPeriodBelongsToSchool($school, $data['session_id'], $data['term_id']);

        // Store the files before opening the transaction: a slow upload should
        // not hold a database lock, and an orphaned file is a smaller problem
        // than a timed-out write.
        $stored = $this->storeEvidence($files, $school->id, $student->id);

        try {
            return DB::transaction(function () use ($school, $student, $data, $stored, $actor) {
                $payment = Payment::create([
                    'school_id' => $school->id,
                    'student_id' => $student->id,
                    'session_id' => $data['session_id'],
                    'term_id' => $data['term_id'],
                    'student_bill_id' => $this->resolveBillId($student, $data),
                    'reference' => $this->sequences->paymentReference($school),
                    'payer_reference' => $data['payer_reference'] ?? null,
                    'amount' => $data['amount'],
                    'method' => $data['method'],
                    'paid_at' => $data['paid_at'],
                    'bank_detail_id' => $this->resolveBankDetailId($school, $data),
                    'note' => $data['note'] ?? null,
                    'source' => Payment::SOURCE_STUDENT_SUBMISSION,
                    'status' => Payment::STATUS_PENDING,
                    'submitted_by_type' => $actor instanceof User
                        ? Payment::ACTOR_USER
                        : Payment::ACTOR_STUDENT,
                    'submitted_by_id' => $actor->getKey(),
                ]);

                foreach ($stored as $file) {
                    PaymentEvidence::create($file + [
                        'payment_id' => $payment->id,
                        'uploaded_by_type' => $actor instanceof User
                            ? Payment::ACTOR_USER
                            : Payment::ACTOR_STUDENT,
                        'uploaded_by_id' => $actor->getKey(),
                    ]);
                }

                $this->audit->log(
                    $school->id,
                    'payment.submitted',
                    'payment',
                    $payment->id,
                    null,
                    $this->snapshot($payment),
                    $actor,
                );

                return $payment->load('evidence');
            });
        } catch (\Throwable $e) {
            // The row never landed, so the uploads are litter. Remove them.
            foreach ($stored as $file) {
                Storage::disk($file['disk'])->delete($file['path']);
            }

            throw $e;
        }
    }

    /**
     * An admin recording a payment themselves -- cash at the desk, a transfer
     * they have already confirmed on the bank statement.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(Student $student, array $data, User $actor): Payment
    {
        $school = $student->school;

        if (! $school) {
            throw ValidationException::withMessages([
                'student' => ['This student is not attached to a school.'],
            ]);
        }

        $this->assertPeriodBelongsToSchool($school, $data['session_id'], $data['term_id']);

        return DB::transaction(function () use ($school, $student, $data, $actor) {
            $payment = Payment::create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'session_id' => $data['session_id'],
                'term_id' => $data['term_id'],
                'student_bill_id' => $this->resolveBillId($student, $data),
                'reference' => $this->sequences->paymentReference($school),
                'payer_reference' => $data['payer_reference'] ?? null,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'paid_at' => $data['paid_at'],
                'bank_detail_id' => $this->resolveBankDetailId($school, $data),
                'note' => $data['note'] ?? null,
                'source' => Payment::SOURCE_ADMIN_MANUAL,
                // Still pending by default. An admin who saw the money can
                // verify it in the same breath, but that is a separate,
                // separately-permissioned act -- not a side effect of typing
                // the payment in.
                'status' => Payment::STATUS_PENDING,
                'submitted_by_type' => Payment::ACTOR_USER,
                'submitted_by_id' => $actor->getKey(),
            ]);

            $this->audit->log(
                $school->id,
                'payment.recorded',
                'payment',
                $payment->id,
                null,
                $this->snapshot($payment),
                $actor,
            );

            return $payment;
        });
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array<string, mixed>>
     */
    private function storeEvidence(array $files, string $schoolId, string $studentId): array
    {
        $stored = [];

        foreach ($files as $file) {
            if (! $file->isValid()) {
                throw ValidationException::withMessages([
                    'evidence' => ['One of the uploaded files could not be read. Try again.'],
                ]);
            }

            // The client's Content-Type is a claim, not a fact. Re-derive the
            // type from the file itself before storing it.
            $mime = $file->getMimeType();

            if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
                throw ValidationException::withMessages([
                    'evidence' => ['Evidence must be a JPG, PNG, WebP image or a PDF.'],
                ]);
            }

            if ($file->getSize() > self::MAX_EVIDENCE_BYTES) {
                throw ValidationException::withMessages([
                    'evidence' => ['Each file must be 5MB or smaller.'],
                ]);
            }

            $path = $file->store("{$schoolId}/{$studentId}", self::EVIDENCE_DISK);

            $stored[] = [
                'disk' => self::EVIDENCE_DISK,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'size_bytes' => $file->getSize(),
            ];
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveBillId(Student $student, array $data): ?string
    {
        return StudentBill::query()
            ->where('student_id', $student->id)
            ->where('session_id', $data['session_id'])
            ->where('term_id', $data['term_id'])
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveBankDetailId(School $school, array $data): ?string
    {
        if (empty($data['bank_detail_id'])) {
            return null;
        }

        $id = BankDetail::query()
            ->whereKey($data['bank_detail_id'])
            ->where('school_id', $school->id)
            ->value('id');

        if (! $id) {
            throw ValidationException::withMessages([
                'bank_detail_id' => ['Select one of this school\'s payment accounts.'],
            ]);
        }

        return $id;
    }

    private function assertPeriodBelongsToSchool(School $school, string $sessionId, string $termId): void
    {
        $valid = \App\Models\Term::query()
            ->whereKey($termId)
            ->where('school_id', $school->id)
            ->where('session_id', $sessionId)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'term_id' => ['Select a term that belongs to this school and session.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Payment $payment): array
    {
        return [
            'reference' => $payment->reference,
            'amount' => (string) $payment->amount,
            'method' => $payment->method,
            'paid_at' => $payment->paid_at?->toDateString(),
            'status' => $payment->status,
            'source' => $payment->source,
        ];
    }
}
