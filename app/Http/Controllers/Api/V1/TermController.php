<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Models\School;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TermController extends Controller
{
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Get term details with subscription status
     */
    public function show(Term $term)
    {
        return response()->json([
            'term' => $term->load('invoices', 'midtermAdditions'),
            'subscription_status' => [
                'requires_subscription' => $term->school->requiresSubscription(),
                'is_demo' => $term->school->isDemo(),
                'can_switch' => $this->subscriptionService->canSwitchTerm($term),
                'switch_message' => $this->subscriptionService->getTermSwitchMessage($term),
                'outstanding_balance' => $term->getOutstandingBalance(),
                'amount_due' => $term->amount_due,
                'amount_paid' => $term->amount_paid,
                'midterm_amount_due' => $term->midterm_amount_due,
                'midterm_amount_paid' => $term->midterm_amount_paid,
            ],
        ]);
    }

    /**
     * Switch to next term
     */
    public function switchTerm(Term $term, Request $request)
    {
        // Get the school
        $school = $term->school;

        // Validate term switch permissions
        if (!$request->user() || $request->user()->school_id !== $school->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if term switch is allowed
        if (!$this->subscriptionService->canSwitchTerm($term)) {
            throw ValidationException::withMessages([
                'term_switch' => $this->subscriptionService->getTermSwitchMessage($term),
            ]);
        }

        // Find next term
        $nextTerm = $term->session->terms()
            ->where('term_number', '>', $term->term_number)
            ->orderBy('term_number')
            ->first();

        if (!$nextTerm) {
            return response()->json(['message' => 'No next term found'], 404);
        }

        // Generate invoice for next term if needed
        if ($school->requiresSubscription() && !$nextTerm->invoice_id) {
            $this->subscriptionService->generateTermInvoice($nextTerm);
        }

        // Update current term in school
        $school->update(['current_term_id' => $nextTerm->id]);

        return response()->json([
            'message' => 'Term switched successfully',
            'current_term' => $nextTerm->load('invoices'),
        ]);
    }

    /**
     * Get school term list with payment status
     */
    public function schoolTerms(School $school, Request $request)
    {
        $terms = $school->terms()
            ->with('session')
            ->orderBy('session_id', 'desc')
            ->orderBy('term_number', 'asc')
            ->paginate($request->input('per_page', 20));

        // Add subscription status for each term
        $terms->getCollection()->transform(function (Term $term) {
            return [
                'id' => $term->id,
                'name' => $term->name,
                'term_number' => $term->term_number,
                'start_date' => $term->start_date,
                'end_date' => $term->end_date,
                'status' => $term->status,
                'subscription' => [
                    'can_switch' => $this->subscriptionService->canSwitchTerm($term),
                    'outstanding_balance' => $term->getOutstandingBalance(),
                    'payment_status' => $term->payment_status,
                ],
            ];
        });

        return response()->json($terms);
    }

    /**
     * Get term payment details
     */
    public function paymentDetails(Term $term)
    {
        if (!$term->school->requiresSubscription()) {
            return response()->json([
                'message' => 'This school is exempt from subscription',
            ]);
        }

        $invoice = $term->invoice;

        return response()->json([
            'invoice' => $invoice,
            'midterm_additions' => $term->midtermAdditions()->with('student')->get(),
            'payment_summary' => [
                'amount_due' => $term->amount_due,
                'amount_paid' => $term->amount_paid,
                'midterm_amount_due' => $term->midterm_amount_due,
                'midterm_amount_paid' => $term->midterm_amount_paid,
                'outstanding_balance' => $term->getOutstandingBalance(),
                'payment_due_date' => $term->payment_due_date,
            ],
        ]);
    }

    /**
     * Send payment reminder
     */
    public function sendPaymentReminder(Term $term)
    {
        if (!$term->school->requiresSubscription()) {
            return response()->json([
                'message' => 'This school is exempt from subscription',
            ]);
        }

        if ($term->allFeesPaid()) {
            return response()->json([
                'message' => 'All fees already paid',
            ]);
        }

        // TODO: Send email reminder to school
        // Mail::send(new PaymentReminderMail($term));

        return response()->json([
            'message' => 'Payment reminder sent',
        ]);
    }
}
