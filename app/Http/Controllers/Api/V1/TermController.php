<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Term;
use App\Models\TermPaymentTransaction;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
        }

        // Update current term in school
        $school->update(['current_term_id' => $nextTerm->id]);

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

        $formattedTerms = $terms->getCollection()
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
        $school = $this->resolveAuthenticatedSchool($request);
        if (! $school || $term->school_id !== $school->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! $term->school->requiresSubscription()) {
            return response()->json([
                'message' => 'This school is exempt from subscription',
            ], 422);
        }

        $outstandingBalance = round((float) $term->getOutstandingBalance(), 2);
        if ($outstandingBalance <= 0) {
            return response()->json([
                'message' => 'No outstanding balance for this term.',
            ], 422);
        }

        $secretKey = trim((string) config('services.paystack.secret_key'));
        $baseUrl = rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');
        if ($secretKey === '') {
            return response()->json([
                'message' => 'Paystack is not configured on the server.',
            ], 500);
        }

        $email = strtolower(
            trim((string) ($request->user()?->email ?? $school->email ?? ''))
        );
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'A school/admin email is required to initialize payment.',
            ]);
        }

        $reference = $this->generatePaystackReference();
        $frontendBase = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        $callbackUrl = $frontendBase . '/settings/payment';
        $amountInKobo = (int) round($outstandingBalance * 100);

        $requestMeta = [
            'scope' => 'term',
            'term_ids' => [$term->id],
            'session_id' => $term->session_id,
        ];

        $transaction = TermPaymentTransaction::create([
            'school_id' => $school->id,
            'term_id' => $term->id,
            'invoice_id' => $term->invoice_id,
            'created_by' => $request->user()?->id,
            'provider' => 'paystack',
            'reference' => $reference,
            'amount' => $outstandingBalance,
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
                    'school_id' => $school->id,
                    'term_id' => $term->id,
                    'invoice_id' => $term->invoice_id,
                ],
            ]);

        $payload = $response->json();
        $isValidPayload = is_array($payload);
        $isSuccessful = $response->ok() && $isValidPayload && (($payload['status'] ?? false) === true);

        if (! $isSuccessful) {
            $message = is_array($payload) && ! empty($payload['message'])
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
                'payment' => $message,
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
            'message' => 'Payment initialized successfully.',
            'reference' => $reference,
            'authorization_url' => (string) ($data['authorization_url'] ?? ''),
            'access_code' => (string) ($data['access_code'] ?? ''),
            'amount' => $outstandingBalance,
            'currency' => 'NGN',
        ]);
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
            ->orderBy('term_number')
            ->get();

        if ($terms->isEmpty()) {
            throw ValidationException::withMessages([
                'session_id' => 'No terms were found for this session.',
            ]);
        }

        $outstandingTerms = $terms
            ->map(function (Term $term) {
                return [
                    'term' => $term,
                    'outstanding' => round((float) $term->getOutstandingBalance(), 2),
                ];
            })
            ->filter(fn (array $row) => $row['outstanding'] > 0)
            ->values();

        if ($outstandingTerms->isEmpty()) {
            throw ValidationException::withMessages([
                'session_id' => 'All terms in this session are already fully paid.',
            ]);
        }

        $totalOutstanding = round(
            $outstandingTerms->sum(fn (array $row) => $row['outstanding']),
            2
        );

        $email = strtolower(
            trim((string) ($request->user()?->email ?? $school->email ?? ''))
        );
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'A school/admin email is required to initialize payment.',
            ]);
        }

        /** @var Term $primaryTerm */
        $primaryTerm = $outstandingTerms[0]['term'];
        $reference = $this->generatePaystackReference('SESSION');
        $frontendBase = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        $callbackUrl = $frontendBase . '/settings/payment';
        $amountInKobo = (int) round($totalOutstanding * 100);

        $termIds = $outstandingTerms->map(fn (array $row) => $row['term']->id)->values()->all();
        $termBreakdown = $outstandingTerms->map(fn (array $row) => [
            'term_id' => $row['term']->id,
            'term_name' => $row['term']->name,
            'amount' => $row['outstanding'],
        ])->values()->all();

        $requestMeta = [
            'scope' => 'session',
            'session_id' => $validated['session_id'],
            'term_ids' => $termIds,
            'breakdown' => $termBreakdown,
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
                    'scope' => 'session',
                    'school_id' => $school->id,
                    'session_id' => $validated['session_id'],
                    'term_ids' => $termIds,
                ],
            ]);

        $payload = $response->json();
        $isValidPayload = is_array($payload);
        $isSuccessful = $response->ok() && $isValidPayload && (($payload['status'] ?? false) === true);

        if (! $isSuccessful) {
            $message = is_array($payload) && ! empty($payload['message'])
                ? (string) $payload['message']
                : 'Unable to initialize session payment at this time.';

            $transaction->update([
                'status' => 'failed',
                'gateway_response' => [
                    'request' => $requestMeta,
                    'initialize' => $isValidPayload ? $payload : ['raw' => (string) $response->body()],
                ],
            ]);

            throw ValidationException::withMessages([
                'payment' => $message,
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
            'message' => 'Session payment initialized successfully.',
            'reference' => $reference,
            'authorization_url' => (string) ($data['authorization_url'] ?? ''),
            'access_code' => (string) ($data['access_code'] ?? ''),
            'amount' => $totalOutstanding,
            'currency' => 'NGN',
            'session_id' => $validated['session_id'],
            'term_count' => count($termIds),
            'breakdown' => $termBreakdown,
        ]);
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

        DB::transaction(function () use ($transaction, $payload, $gatewayData, $school) {
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

            if ($scope === 'session') {
                $sessionId = (string) ($lockedRequestMeta['session_id'] ?? '');
                $termIds = collect($lockedRequestMeta['term_ids'] ?? [])
                    ->filter(fn ($id) => is_string($id) && $id !== '')
                    ->values()
                    ->all();

                if ($sessionId === '' && $termIds === []) {
                    throw ValidationException::withMessages([
                        'payment' => 'Session payment metadata is invalid.',
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
                        'payment' => 'No terms were found to settle for this session payment.',
                    ]);
                }

                foreach ($termsToSettle as $term) {
                    $this->markTermAsFullyPaid($term);
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

    private function formatTerm(Term $term): array
    {
        $sessionName = $term->relationLoaded('session') ? $term->session?->name : null;

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
            'outstanding_balance' => (float) $term->getOutstandingBalance(),
            'payment_due_date' => $term->payment_due_date,
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
}
