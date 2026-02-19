<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentCommission;
use App\Models\Invoice;
use App\Models\Referral;
use App\Models\ReferralRegistration;
use App\Models\School;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermPaymentTransaction;
use App\Services\CommissionService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;

class TermController extends Controller
{
    private SubscriptionService $subscriptionService;
    private CommissionService $commissionService;

    public function __construct(
        SubscriptionService $subscriptionService,
        CommissionService $commissionService
    )
    {
        $this->subscriptionService = $subscriptionService;
        $this->commissionService = $commissionService;
    }

    /**
     * Get term details with subscription status
     */
    public function show(Term $term, Request $request)
    {
        $school = $this->resolveAuthenticatedSchool($request);
        if (! $school || $term->school_id !== $school->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'term' => $this->formatTerm($term->load('invoices', 'midtermAdditions', 'school')),
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
                'is_free_trial_term' => $this->subscriptionService->isFreeTrialTerm($term),
                'free_trial_enabled_for_school' => $this->subscriptionService->isFreeTrialEnabledForSchool($term->school),
                'free_trial_terms_limit' => (int) config('subscription.free_trial_terms', 1),
            ],
        ]);
    }

    /**
     * Switch to next term
     */
    public function switchTerm(Term $term, Request $request)
    {
        $school = $this->resolveAuthenticatedSchool($request);

        if (! $school || $term->school_id !== $school->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if term switch is allowed
        if (! $this->subscriptionService->canSwitchTerm($term)) {
            throw ValidationException::withMessages([
                'term_switch' => $this->subscriptionService->getTermSwitchMessage($term),
            ]);
        }

        // Find next term
        $nextTerm = $term->session->terms()
            ->where('school_id', $school->id)
            ->where('term_number', '>', $term->term_number)
            ->orderBy('term_number')
            ->first();

        if (!$nextTerm) {
            return response()->json(['message' => 'No next term found'], 404);
        }

        // Generate invoice for next term if needed
        if ($school->requiresSubscription() && !$nextTerm->invoice_id) {
            $this->subscriptionService->generateTermInvoice($nextTerm);
            $nextTerm = Term::query()
                ->with(['school', 'invoices', 'session'])
                ->where('id', $nextTerm->id)
                ->first() ?? $nextTerm;
        } else {
            $nextTerm->loadMissing(['school', 'invoices', 'session']);
        }

        $nextTermOutstanding = round((float) $nextTerm->getOutstandingBalance(), 2);
        if ($school->requiresSubscription() && ! $this->subscriptionService->isFreeTrialTerm($nextTerm) && $nextTermOutstanding > 0) {
            throw ValidationException::withMessages([
                'term_switch' => 'Cannot switch to ' . $nextTerm->name . ' until payment is cleared. Outstanding: ₦' . number_format($nextTermOutstanding, 2) . '.',
            ]);
        }

        // Update current term in school
        $school->update(['current_term_id' => $nextTerm->id]);
        Student::query()
            ->where('school_id', $school->id)
            ->where('current_session_id', $nextTerm->session_id)
            ->update([
                'current_term_id' => $nextTerm->id,
            ]);

        return response()->json([
            'message' => 'Term switched successfully',
            'current_term' => $this->formatTerm($nextTerm->load('invoices', 'school')),
        ]);
    }

    /**
     * Get school term list with payment status
     */
    public function schoolTerms(Request $request)
    {
        $school = $this->resolveAuthenticatedSchool($request);
        if (! $school) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $terms = $school->terms()
            ->with(['session', 'invoices', 'school'])
            ->orderBy('start_date', 'desc')
            ->orderBy('term_number', 'asc')
            ->paginate($request->input('per_page', 20));

        $hydratedTerms = $terms->getCollection()
            ->map(function (Term $term) use ($school) {
                $schoolCurrentSessionId = (string) ($school->current_session_id ?? '');
                if (
                    $schoolCurrentSessionId === ''
                    || (string) $term->session_id === $schoolCurrentSessionId
                ) {
                    return $this->ensureTermReadyForPayment($term);
                }

                return $term;
            });

        $formattedTerms = $hydratedTerms
            ->map(fn (Term $term) => $this->formatTerm($term))
            ->values();
        $terms->setCollection($formattedTerms);

        $payload = $terms->toArray();
        $payload['terms'] = $payload['data'] ?? [];

        return response()->json($payload);
    }

    /**
     * Get term payment details
     */
    public function paymentDetails(Term $term, Request $request)
    {
        $school = $this->resolveAuthenticatedSchool($request);
        if (! $school || $term->school_id !== $school->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $term = $this->ensureTermReadyForPayment($term->load(['school', 'invoices']));

        if (! $term->school->requiresSubscription()) {
            return response()->json([
                'message' => 'This school is exempt from subscription',
            ]);
        }

        $invoice = $term->invoice?->refresh();
        $recentTransactions = TermPaymentTransaction::query()
            ->where('school_id', $school->id)
            ->where('term_id', $term->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'term' => $this->formatTerm($term->load('invoices')),
            'invoice' => $invoice ? $this->formatInvoice($invoice) : null,
            'midterm_additions' => $term->midtermAdditions()->with('student')->get(),
            'recent_transactions' => $recentTransactions->map(function (TermPaymentTransaction $transaction) {
                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'amount' => (float) $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'provider' => $transaction->provider,
                    'created_at' => $transaction->created_at,
                    'paid_at' => $transaction->paid_at,
                ];
            }),
            'payment_summary' => [
                'amount_due' => (float) ($term->amount_due ?? 0),
                'amount_paid' => (float) ($term->amount_paid ?? 0),
                'midterm_amount_due' => (float) ($term->midterm_amount_due ?? 0),
                'midterm_amount_paid' => (float) ($term->midterm_amount_paid ?? 0),
                'outstanding_balance' => (float) $term->getOutstandingBalance(),
                'payment_due_date' => $term->payment_due_date,
            ],
        ]);
    }

    /**
     * Send payment reminder
     */
    public function sendPaymentReminder(Term $term, Request $request)
    {
        $school = $this->resolveAuthenticatedSchool($request);
        if (! $school || $term->school_id !== $school->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! $term->school->requiresSubscription()) {
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

    /**
     * Initialize Paystack payment for a term's outstanding balance.
     */
    public function initializePaystackPayment(Term $term, Request $request)
    {
        $validated = $request->validate([
            'include_previous_unpaid' => 'sometimes|boolean',
        ]);

        $school = $this->resolveAuthenticatedSchool($request);
        if (! $school || $term->school_id !== $school->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $term = $this->ensureTermReadyForPayment($term->load(['school', 'invoices']));
        $includePreviousUnpaid = (bool) ($validated['include_previous_unpaid'] ?? false);
        $previousUnpaidTerms = $this->getPreviousUnpaidTerms($term);

        if ($previousUnpaidTerms->isNotEmpty() && ! $includePreviousUnpaid) {
            $paymentLockReason = $this->getPaymentOrderLockReason($term);
            throw ValidationException::withMessages([
                'term_id' => ($paymentLockReason ?? 'Please settle previous term before paying this term.')
                    . ' Or use the "Pay Outstanding + Selected Term" option.',
            ]);
        }

        if (! $term->school->requiresSubscription()) {
            return response()->json([
                'message' => 'This school is exempt from subscription',
            ], 422);
        }

        $currentTermOutstanding = round((float) $term->getOutstandingBalance(), 2);
        if ($currentTermOutstanding <= 0) {
            return response()->json([
                'message' => 'No outstanding balance for this term.',
            ], 422);
        }

        $termsToPay = $includePreviousUnpaid
            ? $previousUnpaidTerms->push($term)->unique('id')->values()
            : collect([$term]);

        $isCombinedPayment = $termsToPay->count() > 1;
        $scope = $isCombinedPayment ? 'outstanding_plus_term' : 'term';
        $purpose = $isCombinedPayment
            ? 'Outstanding terms + selected term payment'
            : 'Single term payment';

        return $this->initializeCheckoutForTerms(
            request: $request,
            school: $school,
            terms: $termsToPay,
            scope: $scope,
            sessionId: $term->session_id,
            referencePrefix: $isCombinedPayment ? 'MIXED' : 'TERM',
            message: $isCombinedPayment
                ? 'Outstanding and selected term payment initialized successfully.'
                : 'Payment initialized successfully.',
            purpose: $purpose,
        );
    }

    /**
     * Initialize one Paystack payment for all outstanding terms in a session.
     */
    public function initializeSessionPaystackPayment(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|uuid',
        ]);

        $school = $this->resolveAuthenticatedSchool($request);
        if (! $school) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $secretKey = trim((string) config('services.paystack.secret_key'));
        $baseUrl = rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');
        if ($secretKey === '') {
            return response()->json([
                'message' => 'Paystack is not configured on the server.',
            ], 500);
        }

        $terms = Term::query()
            ->with('school')
            ->where('school_id', $school->id)
            ->where('session_id', $validated['session_id'])
            ->orderBy('start_date')
            ->orderBy('term_number')
            ->get();

        if ($terms->isEmpty()) {
            throw ValidationException::withMessages([
                'session_id' => 'No terms were found for this session.',
            ]);
        }

        /** @var Term|null $firstTermInSession */
        $firstTermInSession = $terms->first();
        if ($firstTermInSession) {
            $sessionPaymentLockReason = $this->getPaymentOrderLockReason($firstTermInSession);
            if ($sessionPaymentLockReason !== null) {
                throw ValidationException::withMessages([
                    'session_id' => $sessionPaymentLockReason,
                ]);
            }
        }

        return $this->initializeCheckoutForTerms(
            request: $request,
            school: $school,
            terms: $terms,
            scope: 'session',
            sessionId: $validated['session_id'],
            referencePrefix: 'SESSION',
            message: 'Session payment initialized successfully.',
            purpose: 'Outstanding terms in selected session',
        );
    }

    /**
     * Verify Paystack payment by reference.
     */
    public function verifyPaystackPayment(Request $request)
    {
        $validated = $request->validate([
            'reference' => 'required|string|max:100',
        ]);

        $school = $this->resolveAuthenticatedSchool($request);
        if (! $school) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaction = TermPaymentTransaction::query()
            ->where('provider', 'paystack')
            ->where('reference', $validated['reference'])
            ->where('school_id', $school->id)
            ->first();

        if (! $transaction) {
            throw ValidationException::withMessages([
                'reference' => 'Payment reference was not found for this school.',
            ]);
        }

        $existingResponse = is_array($transaction->gateway_response) ? $transaction->gateway_response : [];
        $requestMeta = is_array($existingResponse['request'] ?? null) ? $existingResponse['request'] : [];

        if ($transaction->isSuccessful()) {
            $termIds = collect($requestMeta['term_ids'] ?? [])
                ->filter(fn ($id) => is_string($id) && $id !== '')
                ->values();
            if ($termIds->isEmpty() && is_string($transaction->term_id) && $transaction->term_id !== '') {
                $termIds = collect([$transaction->term_id]);
            }

            if ($termIds->isNotEmpty()) {
                $settledTerms = Term::query()
                    ->with(['invoices', 'invoice', 'school'])
                    ->where('school_id', $school->id)
                    ->whereIn('id', $termIds->all())
                    ->orderBy('start_date')
                    ->orderBy('term_number')
                    ->get();
                $this->applyReferralCommissionsForSettledTerms($school, $settledTerms);
            }

            $term = Term::query()->where('id', $transaction->term_id)->first();
            return response()->json([
                'message' => 'Payment already verified.',
                'term' => $term ? $this->formatTerm($term->load('invoices', 'school')) : null,
                'scope' => (string) ($requestMeta['scope'] ?? 'term'),
            ]);
        }

        $secretKey = trim((string) config('services.paystack.secret_key'));
        $baseUrl = rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');
        if ($secretKey === '') {
            return response()->json([
                'message' => 'Paystack is not configured on the server.',
            ], 500);
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->withToken($secretKey)
            ->get($baseUrl . '/transaction/verify/' . urlencode($transaction->reference));

        $payload = $response->json();
        $isValidPayload = is_array($payload);
        $isSuccessful = $response->ok() && $isValidPayload && (($payload['status'] ?? false) === true);

        if (! $isSuccessful) {
            $message = is_array($payload) && ! empty($payload['message'])
                ? (string) $payload['message']
                : 'Unable to verify payment right now.';

            $transaction->update([
                'status' => 'failed',
                'gateway_response' => [
                    'request' => $requestMeta,
                    'initialize' => $existingResponse['initialize'] ?? null,
                    'verify' => $isValidPayload ? $payload : ['raw' => (string) $response->body()],
                ],
            ]);

            throw ValidationException::withMessages([
                'payment' => $message,
            ]);
        }

        $gatewayData = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $gatewayStatus = strtolower((string) ($gatewayData['status'] ?? ''));
        $mappedStatus = $this->mapPaystackStatus($gatewayStatus);

        if ($mappedStatus !== 'success') {
            $transaction->update([
                'status' => $mappedStatus,
                'gateway_response' => [
                    'request' => $requestMeta,
                    'initialize' => $existingResponse['initialize'] ?? null,
                    'verify' => $payload,
                ],
                'gateway_transaction_id' => (string) ($gatewayData['id'] ?? ''),
            ]);

            throw ValidationException::withMessages([
                'payment' => $gatewayStatus === ''
                    ? 'Payment is not yet successful.'
                    : 'Payment status is ' . $gatewayStatus . '.',
            ]);
        }

        $amountPaid = ((float) ($gatewayData['amount'] ?? 0)) / 100;
        if ($amountPaid + 0.01 < (float) $transaction->amount) {
            $transaction->update([
                'status' => 'failed',
                'gateway_response' => [
                    'request' => $requestMeta,
                    'initialize' => $existingResponse['initialize'] ?? null,
                    'verify' => $payload,
                ],
                'gateway_transaction_id' => (string) ($gatewayData['id'] ?? ''),
            ]);

            throw ValidationException::withMessages([
                'payment' => 'Received payment amount is less than the outstanding balance.',
            ]);
        }

        $settledTermIds = [];

        DB::transaction(function () use ($transaction, $payload, $gatewayData, $school, &$settledTermIds) {
            $lockedTransaction = TermPaymentTransaction::query()
                ->where('id', $transaction->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedTransaction || $lockedTransaction->isSuccessful()) {
                return;
            }

            $lockedResponse = is_array($lockedTransaction->gateway_response)
                ? $lockedTransaction->gateway_response
                : [];
            $lockedRequestMeta = is_array($lockedResponse['request'] ?? null)
                ? $lockedResponse['request']
                : [];
            $scope = (string) ($lockedRequestMeta['scope'] ?? 'term');
            $sessionId = (string) ($lockedRequestMeta['session_id'] ?? '');
            $termIds = collect($lockedRequestMeta['term_ids'] ?? [])
                ->filter(fn ($id) => is_string($id) && $id !== '')
                ->values()
                ->all();
            $isMultiTermScope = $scope === 'session'
                || $scope === 'outstanding_plus_term'
                || count($termIds) > 1;

            if ($isMultiTermScope) {

                if ($sessionId === '' && $termIds === []) {
                    throw ValidationException::withMessages([
                        'payment' => 'Multi-term payment metadata is invalid.',
                    ]);
                }

                $termsQuery = Term::query()
                    ->where('school_id', $school->id);

                if ($termIds !== []) {
                    $termsQuery->whereIn('id', $termIds);
                } else {
                    $termsQuery->where('session_id', $sessionId);
                }

                $termsToSettle = $termsQuery
                    ->lockForUpdate()
                    ->get();

                if ($termsToSettle->isEmpty()) {
                    throw ValidationException::withMessages([
                        'payment' => 'No terms were found to settle for this payment.',
                    ]);
                }

                foreach ($termsToSettle as $term) {
                    $this->markTermAsFullyPaid($term);
                    $settledTermIds[] = (string) $term->id;
                }
            } else {
                $term = Term::query()
                    ->where('id', $lockedTransaction->term_id)
                    ->where('school_id', $school->id)
                    ->lockForUpdate()
                    ->first();

                if (! $term) {
                    throw ValidationException::withMessages([
                        'payment' => 'Term for this payment transaction was not found.',
                    ]);
                }

                $this->markTermAsFullyPaid($term);
                $settledTermIds[] = (string) $term->id;
            }

            $lockedTransaction->update([
                'status' => 'success',
                'gateway_response' => [
                    'request' => $lockedRequestMeta,
                    'initialize' => $lockedResponse['initialize'] ?? null,
                    'verify' => $payload,
                ],
                'gateway_transaction_id' => (string) ($gatewayData['id'] ?? ''),
                'verified_at' => now(),
                'paid_at' => now(),
            ]);
        });

        $settledTerms = Term::query()
            ->with(['invoices', 'invoice', 'school'])
            ->where('school_id', $school->id)
            ->whereIn('id', collect($settledTermIds)->filter()->values()->all())
            ->orderBy('start_date')
            ->orderBy('term_number')
            ->get();
        $this->applyReferralCommissionsForSettledTerms($school, $settledTerms);

        $updatedTerm = Term::query()
            ->with(['invoices', 'school'])
            ->where('id', $transaction->term_id)
            ->first();

        return response()->json([
            'message' => 'Payment verified and applied successfully.',
            'term' => $updatedTerm ? $this->formatTerm($updatedTerm) : null,
            'reference' => $transaction->reference,
            'scope' => (string) ($requestMeta['scope'] ?? 'term'),
        ]);
    }

    /**
     * List school payment history (term and session scope).
     */
    public function paymentHistory(Request $request)
    {
        $school = $this->resolveAuthenticatedSchool($request);
        if (! $school) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transactions = TermPaymentTransaction::query()
            ->with(['term.session'])
            ->where('school_id', $school->id)
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 20));

        $transactions->getCollection()->transform(function (TermPaymentTransaction $transaction) {
            $gatewayResponse = is_array($transaction->gateway_response) ? $transaction->gateway_response : [];
            $requestMeta = is_array($gatewayResponse['request'] ?? null) ? $gatewayResponse['request'] : [];
            $scope = (string) ($requestMeta['scope'] ?? 'term');
            $termIds = collect($requestMeta['term_ids'] ?? [])
                ->filter(fn ($id) => is_string($id) && $id !== '')
                ->values()
                ->all();

            return [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'provider' => $transaction->provider,
                'status' => $transaction->status,
                'amount' => (float) $transaction->amount,
                'currency' => $transaction->currency,
                'scope' => $scope,
                'session_id' => $requestMeta['session_id'] ?? $transaction->term?->session_id,
                'session_name' => $transaction->term?->session?->name,
                'term_id' => $transaction->term_id,
                'term_name' => $transaction->term?->name,
                'term_ids' => $termIds,
                'term_count' => max(1, count($termIds)),
                'created_at' => $transaction->created_at,
                'paid_at' => $transaction->paid_at,
            ];
        });

        $payload = $transactions->toArray();
        $payload['payments'] = $payload['data'] ?? [];

        return response()->json($payload);
    }

    private function resolveAuthenticatedSchool(Request $request): ?School
    {
        $schoolId = (string) ($request->user()?->school_id ?? '');
        if ($schoolId === '') {
            return null;
        }

        return School::query()->find($schoolId);
    }

    private function initializeCheckoutForTerms(
        Request $request,
        School $school,
        Collection $terms,
        string $scope,
        ?string $sessionId,
        string $referencePrefix,
        string $message,
        string $purpose
    ) {
        $secretKey = trim((string) config('services.paystack.secret_key'));
        $baseUrl = rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');
        if ($secretKey === '') {
            return response()->json([
                'message' => 'Paystack is not configured on the server.',
            ], 500);
        }

        $terms = $terms
            ->map(function (Term $term) {
                return [
                    'term' => $term,
                    'outstanding' => round((float) $term->getOutstandingBalance(), 2),
                ];
            })
            ->filter(fn (array $row) => $row['outstanding'] > 0)
            ->values();

        if ($terms->isEmpty()) {
            throw ValidationException::withMessages([
                'term_id' => 'No outstanding balance found for selected payment scope.',
            ]);
        }

        $email = strtolower(trim((string) ($request->user()?->email ?? $school->email ?? '')));
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'A school/admin email is required to initialize payment.',
            ]);
        }

        /** @var Term $primaryTerm */
        $primaryTerm = $terms[0]['term'];
        $reference = $this->generatePaystackReference($referencePrefix);
        $totalOutstanding = round($terms->sum(fn (array $row) => $row['outstanding']), 2);
        $frontendBase = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        $callbackUrl = $frontendBase . '/settings/payment';
        $amountInKobo = (int) round($totalOutstanding * 100);
        $termIds = $terms->map(fn (array $row) => $row['term']->id)->values()->all();
        $termBreakdown = $terms->map(fn (array $row) => [
            'term_id' => $row['term']->id,
            'term_name' => $row['term']->name,
            'amount' => $row['outstanding'],
        ])->values()->all();

        $requestMeta = [
            'scope' => $scope,
            'session_id' => $sessionId,
            'term_ids' => $termIds,
            'breakdown' => $termBreakdown,
            'purpose' => $purpose,
        ];

        $transaction = TermPaymentTransaction::create([
            'school_id' => $school->id,
            'term_id' => $primaryTerm->id,
            'invoice_id' => $primaryTerm->invoice_id,
            'created_by' => $request->user()?->id,
            'provider' => 'paystack',
            'reference' => $reference,
            'amount' => $totalOutstanding,
            'currency' => 'NGN',
            'status' => 'initialized',
            'gateway_response' => [
                'request' => $requestMeta,
            ],
        ]);

        $response = Http::timeout(20)
            ->acceptJson()
            ->withToken($secretKey)
            ->post($baseUrl . '/transaction/initialize', [
                'email' => $email,
                'amount' => $amountInKobo,
                'reference' => $reference,
                'currency' => 'NGN',
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'scope' => $scope,
                    'school_id' => $school->id,
                    'session_id' => $sessionId,
                    'term_ids' => $termIds,
                    'purpose' => $purpose,
                ],
            ]);

        $payload = $response->json();
        $isValidPayload = is_array($payload);
        $isSuccessful = $response->ok() && $isValidPayload && (($payload['status'] ?? false) === true);

        if (! $isSuccessful) {
            $errorMessage = is_array($payload) && ! empty($payload['message'])
                ? (string) $payload['message']
                : 'Unable to initialize payment at this time.';

            $transaction->update([
                'status' => 'failed',
                'gateway_response' => [
                    'request' => $requestMeta,
                    'initialize' => $isValidPayload ? $payload : ['raw' => (string) $response->body()],
                ],
            ]);

            throw ValidationException::withMessages([
                'payment' => $errorMessage,
            ]);
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $transaction->update([
            'status' => 'pending',
            'access_code' => (string) ($data['access_code'] ?? ''),
            'authorization_url' => (string) ($data['authorization_url'] ?? ''),
            'gateway_response' => [
                'request' => $requestMeta,
                'initialize' => $payload,
            ],
        ]);

        return response()->json([
            'message' => $message,
            'reference' => $reference,
            'authorization_url' => (string) ($data['authorization_url'] ?? ''),
            'access_code' => (string) ($data['access_code'] ?? ''),
            'amount' => $totalOutstanding,
            'currency' => 'NGN',
            'scope' => $scope,
            'session_id' => $sessionId,
            'term_count' => count($termIds),
            'breakdown' => $termBreakdown,
            'purpose' => $purpose,
        ]);
    }

    private function formatTerm(Term $term): array
    {
        $paymentLockReason = $this->getPaymentOrderLockReason($term);
        $sessionName = $term->relationLoaded('session') ? $term->session?->name : null;
        $invoices = $term->relationLoaded('invoices') ? $term->invoices : collect();

        $originalSnapshot = (int) ($term->student_count_snapshot ?? 0);
        if ($originalSnapshot <= 0) {
            $originalSnapshot = (int) $invoices
                ->where('invoice_type', 'original')
                ->sum(fn ($invoice) => (int) ($invoice->student_count ?? 0));
        }

        $midtermStudentCount = (int) $invoices
            ->where('invoice_type', 'midterm_addition')
            ->sum(fn ($invoice) => (int) ($invoice->student_count ?? 0));

        $totalStudentsBilled = max(0, $originalSnapshot + $midtermStudentCount);
        $totalDue = (float) (($term->amount_due ?? 0) + ($term->midterm_amount_due ?? 0));
        $totalPaid = (float) (($term->amount_paid ?? 0) + ($term->midterm_amount_paid ?? 0));

        if ($totalStudentsBilled > 0 && $totalDue > 0) {
            $studentsPaidEstimate = (int) floor(($totalPaid / $totalDue) * $totalStudentsBilled);
        } else {
            $studentsPaidEstimate = 0;
        }

        if ((string) ($term->payment_status ?? '') === 'paid' || $term->getOutstandingBalance() <= 0) {
            $studentsPaidEstimate = $totalStudentsBilled;
        }

        $studentsPaidEstimate = min($totalStudentsBilled, max(0, $studentsPaidEstimate));
        $studentsLeftForPayment = max(0, $totalStudentsBilled - $studentsPaidEstimate);

        return [
            'id' => $term->id,
            'name' => $term->name,
            'session_id' => $term->session_id,
            'session_name' => $sessionName,
            'term_number' => $term->term_number,
            'start_date' => $term->start_date,
            'end_date' => $term->end_date,
            'status' => $term->status,
            'payment_status' => (string) ($term->payment_status ?? 'pending'),
            'amount_due' => (float) ($term->amount_due ?? 0),
            'amount_paid' => (float) ($term->amount_paid ?? 0),
            'midterm_amount_due' => (float) ($term->midterm_amount_due ?? 0),
            'midterm_amount_paid' => (float) ($term->midterm_amount_paid ?? 0),
            'is_free_trial_term' => $this->subscriptionService->isFreeTrialTerm($term),
            'free_trial_enabled_for_school' => $this->subscriptionService->isFreeTrialEnabledForSchool($term->school),
            'student_count_snapshot' => $originalSnapshot,
            'midterm_student_count' => $midtermStudentCount,
            'total_students_billed' => $totalStudentsBilled,
            'students_paid_estimate' => $studentsPaidEstimate,
            'students_left_for_payment' => $studentsLeftForPayment,
            'outstanding_balance' => (float) $term->getOutstandingBalance(),
            'payment_due_date' => $term->payment_due_date,
            'can_pay_term' => $paymentLockReason === null,
            'payment_lock_reason' => $paymentLockReason,
            'can_switch' => $this->subscriptionService->canSwitchTerm($term),
            'subscription' => [
                'can_switch' => $this->subscriptionService->canSwitchTerm($term),
                'outstanding_balance' => (float) $term->getOutstandingBalance(),
                'payment_status' => (string) ($term->payment_status ?? 'pending'),
            ],
            'invoices' => $term->relationLoaded('invoices')
                ? $term->invoices->map(fn ($invoice) => $this->formatInvoice($invoice))->values()
                : [],
        ];
    }

    private function formatInvoice($invoice): array
    {
        $amountDue = (float) ($invoice->total_amount ?? 0);
        $amountPaid = $invoice->status === 'paid' ? $amountDue : 0.0;

        return [
            'id' => $invoice->id,
            'invoice_type' => $invoice->invoice_type,
            'student_count' => (int) ($invoice->student_count ?? 0),
            'price_per_student' => (float) ($invoice->price_per_student ?? 0),
            'amount_due' => $amountDue,
            'amount_paid' => $amountPaid,
            'payment_status' => $invoice->status,
            'status' => $invoice->status,
            'due_date' => $invoice->due_date,
            'paid_at' => $invoice->paid_at,
            'created_at' => $invoice->created_at,
        ];
    }

    private function generatePaystackReference(string $prefix = 'TERM'): string
    {
        do {
            $reference = strtoupper($prefix) . '-' . strtoupper(Str::random(16));
        } while (TermPaymentTransaction::where('reference', $reference)->exists());

        return $reference;
    }

    private function mapPaystackStatus(string $gatewayStatus): string
    {
        return match ($gatewayStatus) {
            'success' => 'success',
            'abandoned' => 'abandoned',
            'failed', 'reversed' => 'failed',
            default => 'pending',
        };
    }

    private function markTermAsFullyPaid(Term $term): void
    {
        $term->forceFill([
            'amount_paid' => (float) ($term->amount_due ?? 0),
            'midterm_amount_paid' => (float) ($term->midterm_amount_due ?? 0),
            'payment_status' => 'paid',
        ])->save();

        $term->invoices()
            ->whereIn('status', ['draft', 'sent', 'partial'])
            ->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

        $term->midtermAdditions()
            ->where('status', 'pending_payment')
            ->update(['status' => 'paid']);
    }

    /**
     * Ensure current term has payable billing state when free trial is disabled.
     */
    private function ensureTermReadyForPayment(Term $term): Term
    {
        $term->loadMissing(['school', 'invoices', 'midtermAdditions']);

        if (! $term->school || ! $term->school->requiresSubscription()) {
            return $term;
        }

        if ($this->subscriptionService->isFreeTrialTerm($term)) {
            return $term;
        }

        $originalInvoice = $term->invoices
            ->first(fn ($invoice) => (string) ($invoice->invoice_type ?? '') === 'original');

        if (! $originalInvoice) {
            $this->subscriptionService->generateTermInvoice($term);

            return Term::query()
                ->with(['session', 'invoices', 'school'])
                ->find($term->id) ?? $term;
        }

        $pricePerStudent = max(0, (int) config('subscription.price_per_student', 500));
        $studentCountSnapshot = (int) ($term->student_count_snapshot ?? 0);
        if ($studentCountSnapshot <= 0) {
            $studentCountSnapshot = (int) $term->students()->count();
        }

        $expectedOriginalAmount = (float) ($studentCountSnapshot * $pricePerStudent);
        $originalInvoiceAmount = (float) ($originalInvoice->total_amount ?? 0);

        if ($originalInvoiceAmount <= 0 && $expectedOriginalAmount > 0) {
            $originalInvoice->forceFill([
                'student_count' => $studentCountSnapshot,
                'price_per_student' => $pricePerStudent,
                'total_amount' => $expectedOriginalAmount,
                'status' => 'draft',
                'paid_at' => null,
            ])->save();

            $term->forceFill([
                'invoice_id' => $originalInvoice->id,
                'student_count_snapshot' => $studentCountSnapshot,
                'amount_due' => $expectedOriginalAmount,
                'amount_paid' => 0,
                'payment_status' => 'invoiced',
            ])->save();
        } elseif ((float) ($term->amount_due ?? 0) <= 0 && $originalInvoiceAmount > 0) {
            $term->forceFill([
                'invoice_id' => $originalInvoice->id,
                'student_count_snapshot' => max($studentCountSnapshot, (int) ($originalInvoice->student_count ?? 0)),
                'amount_due' => $originalInvoiceAmount,
                'payment_status' => (float) ($term->amount_paid ?? 0) >= $originalInvoiceAmount ? 'paid' : 'invoiced',
            ])->save();
        }

        $baseBilledStudents = max(
            (int) ($term->student_count_snapshot ?? 0),
            (int) ($originalInvoice->student_count ?? 0),
        );
        $sessionStudents = Student::query()
            ->where('school_id', $term->school_id)
            ->where('current_session_id', $term->session_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id']);
        $currentStudentIds = $sessionStudents
            ->pluck('id')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values();
        $currentStudentCount = $currentStudentIds->count();

        if (
            $pricePerStudent > 0
            && $currentStudentCount > $baseBilledStudents
            && (string) ($originalInvoice->status ?? '') !== 'paid'
        ) {
            $nextStudentCount = $currentStudentCount;
            $nextOriginalAmount = (float) ($nextStudentCount * $pricePerStudent);
            $nextAmountPaid = min((float) ($term->amount_paid ?? 0), $nextOriginalAmount);
            $nextMidtermDue = (float) ($term->midterm_amount_due ?? 0);
            $nextMidtermPaid = (float) ($term->midterm_amount_paid ?? 0);
            $nextOutstanding = max(0, ($nextOriginalAmount + $nextMidtermDue) - ($nextAmountPaid + $nextMidtermPaid));

            $originalInvoice->forceFill([
                'student_count' => $nextStudentCount,
                'price_per_student' => $pricePerStudent,
                'total_amount' => $nextOriginalAmount,
            ])->save();

            $term->forceFill([
                'student_count_snapshot' => $nextStudentCount,
                'amount_due' => $nextOriginalAmount,
                'amount_paid' => $nextAmountPaid,
                'payment_status' => $nextOutstanding <= 0
                    ? 'paid'
                    : ($nextAmountPaid > 0 || $nextMidtermPaid > 0 ? 'partial' : 'invoiced'),
            ])->save();

            $baseBilledStudents = $nextStudentCount;
        }

        $midtermBilledStudentIds = $term->midtermAdditions
            ->pluck('student_id')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values();
        $midtermBilledStudents = $midtermBilledStudentIds->count();
        $unbilledStudentsCount = $currentStudentCount - ($baseBilledStudents + $midtermBilledStudents);

        if ($pricePerStudent > 0 && $unbilledStudentsCount > 0) {
            $alreadyMidtermBilled = $midtermBilledStudentIds->all();
            $candidateStudentIds = $currentStudentIds
                ->filter(fn ($id) => ! in_array($id, $alreadyMidtermBilled, true))
                ->take($unbilledStudentsCount)
                ->values()
                ->all();

            if ($candidateStudentIds !== []) {
                $this->subscriptionService->generateMidtermInvoice($term, $candidateStudentIds);
            }
        }

        return Term::query()
            ->with(['session', 'invoices', 'school'])
            ->find($term->id) ?? $term;
    }

    private function getPaymentOrderLockReason(Term $term): ?string
    {
        $term->loadMissing(['school', 'session']);

        if (! $term->school || ! $term->school->requiresSubscription()) {
            return null;
        }

        $previousUnpaidTerm = $this->getPreviousUnpaidTerms($term)->last();
        if (! $previousUnpaidTerm) {
            return null;
        }

        $previousSessionName = $previousUnpaidTerm->session?->name;
        $context = $previousSessionName
            ? $previousUnpaidTerm->name . ' (' . $previousSessionName . ')'
            : $previousUnpaidTerm->name;
        $outstanding = number_format($previousUnpaidTerm->getOutstandingBalance(), 2);

        return 'Please settle ' . $context . ' before paying ' . $term->name . '. Outstanding: ₦' . $outstanding . '.';
    }

    private function getPreviousUnpaidTerms(Term $term): Collection
    {
        $query = Term::query()
            ->with('session')
            ->where('school_id', $term->school_id)
            ->where('id', '!=', $term->id)
            ->whereRaw(
                '((COALESCE(amount_due, 0) + COALESCE(midterm_amount_due, 0)) - (COALESCE(amount_paid, 0) + COALESCE(midterm_amount_paid, 0))) > 0'
            );

        if ($term->start_date) {
            $query->where(function ($inner) use ($term) {
                $inner
                    ->where('start_date', '<', $term->start_date)
                    ->orWhere(function ($sameDate) use ($term) {
                        $sameDate
                            ->where('start_date', '=', $term->start_date)
                            ->where('term_number', '<', (int) ($term->term_number ?? 0));
                    });
            });
        } else {
            $query
                ->where('session_id', $term->session_id)
                ->where('term_number', '<', (int) ($term->term_number ?? 0));
        }

        return $query
            ->orderBy('start_date')
            ->orderBy('term_number')
            ->get();
    }

    private function applyReferralCommissionsForSettledTerms(School $school, Collection $terms): void
    {
        if ($terms->isEmpty()) {
            return;
        }

        $registration = ReferralRegistration::query()
            ->with('referral')
            ->where('school_id', $school->id)
            ->first();

        $referral = $registration?->referral;
        if (! $referral) {
            $referral = Referral::query()
                ->where('school_id', $school->id)
                ->first();
        }

        if (! $referral) {
            return;
        }

        $createdCount = 0;
        $firstCommissionAmount = null;
        $hasSuccessfulSettlement = false;

        foreach ($terms as $term) {
            $termTotalDue = (float) (($term->amount_due ?? 0) + ($term->midterm_amount_due ?? 0));
            if ($termTotalDue > 0 && (float) $term->getOutstandingBalance() <= 0.0) {
                $hasSuccessfulSettlement = true;
            }

            $invoice = $term->invoices
                ->first(fn ($row) => (string) ($row->invoice_type ?? '') === 'original')
                ?? $term->invoice;

            if (! $invoice instanceof Invoice) {
                continue;
            }

            $alreadyCreated = AgentCommission::query()
                ->where('referral_id', $referral->id)
                ->where('invoice_id', $invoice->id)
                ->exists();

            if ($alreadyCreated) {
                continue;
            }

            $invoice->setRelation('school', $school);
            $commission = $this->commissionService->processCommission($invoice, $referral->fresh());

            if ($commission) {
                $createdCount++;
                if ($firstCommissionAmount === null) {
                    $firstCommissionAmount = (float) ($invoice->total_amount ?? 0);
                }
            }
        }

        if ($registration && ($createdCount > 0 || $hasSuccessfulSettlement)) {
            $updates = [
                'active_at' => now(),
            ];

            if (! $registration->paid_at) {
                $updates['paid_at'] = now();
                $updates['first_payment_amount'] = $firstCommissionAmount ?? (float) ($registration->first_payment_amount ?? 0);
            }

            $registration->update($updates);
        }

        if ($hasSuccessfulSettlement) {
            $referralStatus = strtolower((string) ($referral->status ?? ''));
            if (! in_array($referralStatus, ['paid', 'active'], true)) {
                $referral->update([
                    'status' => 'paid',
                    'paid_at' => $referral->paid_at ?? now(),
                ]);
            } elseif (! $referral->paid_at) {
                $referral->update([
                    'paid_at' => now(),
                ]);
            }
        }
    }
}
