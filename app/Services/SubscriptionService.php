<?php

namespace App\Services;

use App\Models\School;
use App\Models\Term;
use App\Models\Invoice;
use App\Models\MidtermStudentAddition;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    private int $pricePerStudent;
    private int $invoiceGenerationDaysBefore;

    public function __construct()
    {
        $this->pricePerStudent = (int) config('subscription.price_per_student', 500);
        $this->invoiceGenerationDaysBefore = (int) config('subscription.invoice_generation_days_before', 14);
    }

    /**
     * Check if term switch is allowed
     */
    public function canSwitchTerm(Term $term): bool
    {
        // Demo schools can always switch
        if ($term->school->isDemo()) {
            return true;
        }

        // Check if all fees are paid
        return $term->allFeesPaid();
    }

    /**
     * Get term switch validation message
     */
    public function getTermSwitchMessage(Term $term): string
    {
        if ($this->canSwitchTerm($term)) {
            return '✓ All fees paid. Ready to switch term.';
        }

        $outstanding = $term->getOutstandingBalance();
        $originalAmount = $term->amount_due;
        $midtermAmount = $term->midterm_amount_due;

        $message = "✗ Unpaid balance: ₦" . number_format($outstanding, 2);
        $message .= "\n(Original: ₦" . number_format($originalAmount, 2);
        $message .= " + Mid-term admissions: ₦" . number_format($midtermAmount, 2) . ")";

        return $message;
    }

    /**
     * Generate invoice for a term
     */
    public function generateTermInvoice(Term $term): ?Invoice
    {
        // Skip for demo schools
        if ($term->school->isDemo()) {
            return null;
        }

        // Don't generate if already exists
        if ($term->invoice_id) {
            return Invoice::find($term->invoice_id);
        }

        $studentCount = $term->student_count_snapshot ?? $term->school->students()->count();

        $invoice = Invoice::create([
            'school_id' => $term->school_id,
            'term_id' => $term->id,
            'invoice_type' => 'original',
            'student_count' => $studentCount,
            'price_per_student' => $this->pricePerStudent,
            'total_amount' => $studentCount * $this->pricePerStudent,
            'status' => 'draft',
            'due_date' => $term->start_date->subDays($this->invoiceGenerationDaysBefore),
        ]);

        // Update term with invoice
        $term->update([
            'invoice_id' => $invoice->id,
            'student_count_snapshot' => $studentCount,
            'amount_due' => $invoice->total_amount,
            'payment_due_date' => $invoice->due_date,
            'payment_status' => 'invoiced',
        ]);

        return $invoice;
    }

    /**
     * Generate invoice for mid-term student addition
     */
    public function generateMidtermInvoice(Term $term, array $studentIds): ?Invoice
    {
        // Skip for demo schools
        if ($term->school->isDemo()) {
            return null;
        }

        $totalAmount = count($studentIds) * $this->pricePerStudent;

        $invoice = Invoice::create([
            'school_id' => $term->school_id,
            'term_id' => $term->id,
            'invoice_type' => 'midterm_addition',
            'student_count' => count($studentIds),
            'price_per_student' => $this->pricePerStudent,
            'total_amount' => $totalAmount,
            'status' => 'draft',
            'due_date' => now()->addDays(7), // Due in 7 days
        ]);

        // Create mid-term addition records
        foreach ($studentIds as $studentId) {
            MidtermStudentAddition::create([
                'term_id' => $term->id,
                'school_id' => $term->school_id,
                'student_id' => $studentId,
                'invoice_id' => $invoice->id,
                'status' => 'pending_payment',
                'price_per_student' => $this->pricePerStudent,
                'admission_date' => now()->toDateString(),
            ]);
        }

        // Update term
        $term->increment('midterm_amount_due', $totalAmount);
        $term->update(['has_midterm_additions' => true]);

        return $invoice;
    }

    /**
     * Record payment for invoice
     */
    public function recordPayment(Invoice $invoice, float $amount): void
    {
        DB::transaction(function () use ($invoice, $amount) {
            $invoice->update([
                'status' => $amount >= $invoice->total_amount ? 'paid' : 'partial',
                'paid_at' => now(),
            ]);

            // Update corresponding term payment
            if ($invoice->invoice_type === 'original' && $invoice->term) {
                $paidAmount = ($invoice->term->amount_paid ?? 0) + $amount;
                $invoice->term->update([
                    'amount_paid' => $paidAmount,
                    'payment_status' => $paidAmount >= $invoice->total_amount ? 'paid' : 'partial',
                ]);
            } elseif ($invoice->invoice_type === 'midterm_addition' && $invoice->term) {
                $paidAmount = ($invoice->term->midterm_amount_paid ?? 0) + $amount;
                $invoice->term->update([
                    'midterm_amount_paid' => $paidAmount,
                ]);

                // Mark mid-term additions as paid
                if ($paidAmount >= $invoice->total_amount) {
                    MidtermStudentAddition::where('invoice_id', $invoice->id)
                        ->update(['status' => 'paid']);
                }
            }
        });
    }

    /**
     * Get commission configuration
     */
    public function getCommissionConfig(): array
    {
        return [
            'percentage' => (int) config('commission.percentage', 12),
            'payment_count' => (int) config('commission.payment_count', 1),
        ];
    }

    /**
     * Calculate commission for a payment
     */
    public function calculateCommission(float $amount): float
    {
        $percentage = $this->getCommissionConfig()['percentage'];
        return $amount * ($percentage / 100);
    }
}
