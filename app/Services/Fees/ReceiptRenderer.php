<?php

namespace App\Services\Fees;

use App\Models\Payment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Renders a verified payment as a PDF receipt, mirroring the result-slip
 * renderer in StudentAuthController (same Dompdf options, same
 * attachment-header shape).
 */
class ReceiptRenderer
{
    public function __construct(private readonly PaymentAllocationService $allocations) {}

    public function render(Payment $payment): string
    {
        if (! $payment->isVerified()) {
            throw ValidationException::withMessages([
                'payment' => ['A receipt can only be generated for a verified payment.'],
            ]);
        }

        $payment->loadMissing([
            'student.school_class',
            'student.class_arm',
            'school',
            'session',
            'term',
            'verifier',
            'allocations.billItem',
        ]);

        $student = $payment->student;
        $studentName = trim(collect([$student->first_name, $student->last_name])->filter()->implode(' '));
        $className = trim(collect([
            $student->school_class?->name,
            $student->class_arm?->name,
        ])->filter()->implode(' '));

        $allocations = $payment->allocations->map(fn ($allocation) => [
            'name' => $allocation->billItem?->name ?? 'Fee',
            'amount' => (string) $allocation->amount,
        ])->all();

        $html = View::make('payment-receipt', [
            'payment' => $payment,
            'school' => $payment->school,
            'student' => $student,
            'studentName' => $studentName ?: 'Student',
            'className' => $className ?: '—',
            'methodLabel' => ucwords(str_replace('_', ' ', $payment->method)),
            'allocations' => $allocations,
            'unallocated' => $payment->unallocatedAmount(),
            'verifierName' => $payment->verifier?->name ?? 'the school',
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('defaultMediaType', 'print');
        $options->set('isRemoteEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('a4', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    public function filename(Payment $payment): string
    {
        return collect([
            Str::slug($payment->receipt_number ?: $payment->reference),
            'receipt',
        ])->implode('-').'.pdf';
    }
}
