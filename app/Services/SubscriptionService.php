<?php

namespace App\Services;

use App\Models\School;
use App\Models\Term;
use App\Models\Invoice;
use App\Models\MidtermStudentAddition;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    private int $pricePerStudent;
    private int $invoiceGenerationDaysBefore;
    private bool $freeTrialEnabled;
    private int $freeTrialTerms;
    private bool $freeTrialOptionalPerSchool;

    public function __construct()
    {
        $this->pricePerStudent = (int) config('subscription.price_per_student', 500);
        $this->invoiceGenerationDaysBefore = (int) config('subscription.invoice_generation_days_before', 14);
        $this->freeTrialEnabled = (bool) config('subscription.free_trial_enabled', false);
        $this->freeTrialTerms = max(0, (int) config('subscription.free_trial_terms', 1));
        $this->freeTrialOptionalPerSchool = (bool) config('subscription.free_trial_optional_per_school', true);
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

        $studentCount = (int) ($term->student_count_snapshot ?? $term->school->students()->count());
        $isFreeTrialTerm = $this->isFreeTrialTerm($term);
        $pricePerStudent = $isFreeTrialTerm ? 0 : $this->pricePerStudent;
        $totalAmount = $studentCount * $pricePerStudent;
        $invoiceStatus = $isFreeTrialTerm ? 'paid' : 'draft';
        $dueDate = $term->start_date->subDays($this->invoiceGenerationDaysBefore);

        $invoice = Invoice::create([
            'school_id' => $term->school_id,
            'term_id' => $term->id,
            'invoice_type' => 'original',
            'student_count' => $studentCount,
            'price_per_student' => $pricePerStudent,
            'total_amount' => $totalAmount,
            'status' => $invoiceStatus,
            'due_date' => $dueDate,
            'paid_at' => $isFreeTrialTerm ? now() : null,
        ]);

        // Update term with invoice
        $term->update([
            'invoice_id' => $invoice->id,
            'student_count_snapshot' => $studentCount,
            'amount_due' => $invoice->total_amount,
            'payment_due_date' => $invoice->due_date,
            'amount_paid' => $isFreeTrialTerm ? $invoice->total_amount : (float) ($term->amount_paid ?? 0),
            'payment_status' => $isFreeTrialTerm ? 'paid' : 'invoiced',
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

        $isFreeTrialTerm = $this->isFreeTrialTerm($term);
        $pricePerStudent = $isFreeTrialTerm ? 0 : $this->pricePerStudent;
        $totalAmount = count($studentIds) * $pricePerStudent;
        $invoiceStatus = $isFreeTrialTerm ? 'paid' : 'draft';
        $additionStatus = $isFreeTrialTerm ? 'paid' : 'pending_payment';

        $invoice = Invoice::create([
            'school_id' => $term->school_id,
            'term_id' => $term->id,
            'invoice_type' => 'midterm_addition',
            'student_count' => count($studentIds),
            'price_per_student' => $pricePerStudent,
            'total_amount' => $totalAmount,
            'status' => $invoiceStatus,
            'due_date' => now()->addDays(7), // Due in 7 days
            'paid_at' => $isFreeTrialTerm ? now() : null,
        ]);

        // Create mid-term addition records
        foreach ($studentIds as $studentId) {
            MidtermStudentAddition::create([
                'term_id' => $term->id,
                'school_id' => $term->school_id,
                'student_id' => $studentId,
                'invoice_id' => $invoice->id,
                'status' => $additionStatus,
                'price_per_student' => $pricePerStudent,
                'admission_date' => now()->toDateString(),
            ]);
        }

        // Update term
        $term->increment('midterm_amount_due', $totalAmount);
        if ($isFreeTrialTerm && $totalAmount > 0) {
            $term->increment('midterm_amount_paid', $totalAmount);
        }
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

    public function isFreeTrialEnabledForSchool(School $school): bool
    {
        if (! $this->freeTrialEnabled || $this->freeTrialTerms <= 0 || $school->isDemo()) {
            return false;
        }

        if (! $this->freeTrialOptionalPerSchool) {
            return true;
        }

        return (bool) ($school->enable_free_trial ?? false);
    }

    public function isFreeTrialTerm(Term $term): bool
    {
        $termNumber = (int) ($term->term_number ?? 0);
        if ($termNumber <= 0 || $termNumber > $this->freeTrialTerms) {
            return false;
        }

        $school = $term->relationLoaded('school') && $term->school
            ? $term->school
            : School::query()->find($term->school_id);

        if (! $school) {
            return false;
        }

        return $this->isFreeTrialEnabledForSchool($school);
    }
}
