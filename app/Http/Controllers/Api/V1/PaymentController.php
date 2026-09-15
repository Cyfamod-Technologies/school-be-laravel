<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\PaymentEvidence;
use App\Models\School;
use App\Models\Student;
use App\Services\Fees\PaymentAllocationService;
use App\Services\Fees\PaymentSubmissionService;
use App\Services\Fees\PaymentVerificationService;
use App\Services\Fees\ReceiptRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The verification queue and everything downstream of it.
 *
 * @OA\Tag(
 *     name="school-v2.5",
 *     description="Fees - assignments and bills"
 * )
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentSubmissionService $submissions,
        private readonly PaymentVerificationService $verification,
        private readonly PaymentAllocationService $allocations,
        private readonly ReceiptRenderer $receipts,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/fees/payments",
     *     tags={"school-v2.5"},
     *     summary="List payments",
     *     description="Filter by status to get the verification queue (pending_verification) or the collections list (verified).",
     *
     *     @OA\Response(response=200, description="Payments returned")
     * )
     */
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'finance.payments.view');

        $school = $this->requireSchool($request);
        if (! $school instanceof School) {
            return $school;
        }

        $perPage = max((int) $request->input('per_page', 15), 1);

        $payments = Payment::query()
            ->where('school_id', $school->id)
            ->with([
                'student:id,admission_no,first_name,last_name,school_class_id,class_arm_id',
                'student.school_class:id,name',
                'student.class_arm:id,name',
                'evidence',
                'session:id,name',
                'term:id,name',
                'bankDetail:id,bank_name,account_name',
                'verifier:id,name',
                'rejecter:id,name',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('session_id'), fn ($q) => $q->where('session_id', $request->input('session_id')))
            ->when($request->filled('term_id'), fn ($q) => $q->where('term_id', $request->input('term_id')))
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->input('student_id')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->input('method')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('paid_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('paid_at', '<=', $request->input('to')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference', 'like', "%{$search}%")
                        ->orWhere('payer_reference', 'like', "%{$search}%")
                        ->orWhere('receipt_number', 'like', "%{$search}%")
                        ->orWhereHas('student', fn ($student) => $student
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('admission_no', 'like', "%{$search}%"));
                });
            })
            // Oldest first when reviewing the queue: a parent who submitted
            // three days ago should not sit behind this morning's uploads.
            ->orderBy(
                $request->input('status') === Payment::STATUS_PENDING ? 'created_at' : 'paid_at',
                $request->input('status') === Payment::STATUS_PENDING ? 'asc' : 'desc',
            )
            ->paginate($perPage)
            ->withQueryString();

        $payments->setCollection($payments->getCollection()->map(
            fn (Payment $payment) => new PaymentResource($payment, true)
        ));

        return response()->json($payments);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/fees/payments/{payment}",
     *     tags={"school-v2.5"},
     *     summary="One payment, with evidence and allocations",
     *
     *     @OA\Response(response=200, description="Payment returned"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, Payment $payment)
    {
        $this->ensurePermission($request, 'finance.payments.view');

        if ($payment->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json(['data' => $this->present($payment)]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/fees/payments",
     *     tags={"school-v2.5"},
     *     summary="Record a payment on a student's behalf",
     *     description="Creates a pending payment. Verifying it is a separate, separately-permissioned step.",
     *
     *     @OA\Response(response=201, description="Recorded")
     * )
     */
    public function store(Request $request)
    {
        $this->ensurePermission($request, 'finance.payments.record');

        $school = $this->requireSchool($request);
        if (! $school instanceof School) {
            return $school;
        }

        $validated = $request->validate([
            'student_id' => 'required|uuid',
            'amount' => 'required|numeric|min:1',
            'method' => ['required', 'string', Rule::in(Payment::METHODS)],
            'paid_at' => 'required|date|before_or_equal:today',
            'session_id' => 'required|uuid',
            'term_id' => 'required|uuid',
            'payer_reference' => 'nullable|string|max:100',
            'bank_detail_id' => 'nullable|uuid',
            'note' => 'nullable|string|max:1000',
        ]);

        $student = Student::query()
            ->whereKey($validated['student_id'])
            ->where('school_id', $school->id)
            ->first();

        if (! $student) {
            return response()->json(['message' => 'Student not found in this school.'], 404);
        }

        $payment = $this->submissions->record($student, $validated, $request->user());

        return response()->json([
            'message' => 'Payment recorded. It is awaiting verification.',
            'data' => $this->present($payment),
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/fees/payments/{payment}/approve",
     *     tags={"school-v2.5"},
     *     summary="Verify a payment",
     *     description="Issues a receipt number, allocates the payment across unpaid fees, and updates the student's balance.",
     *
     *     @OA\Response(response=200, description="Verified"),
     *     @OA\Response(response=422, description="Already resolved")
     * )
     */
    public function approve(Request $request, Payment $payment)
    {
        $this->ensurePermission($request, 'finance.payments.verify');

        if ($payment->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $payment = $this->verification->approve($payment, $request->user());

        return response()->json([
            'message' => 'Payment verified.',
            'data' => $this->present($payment),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/fees/payments/{payment}/reject",
     *     tags={"school-v2.5"},
     *     summary="Reject a payment",
     *     description="The record is kept for audit; the reason is shown to the student.",
     *
     *     @OA\Response(response=200, description="Rejected"),
     *     @OA\Response(response=422, description="Already resolved, or no reason given")
     * )
     */
    public function reject(Request $request, Payment $payment)
    {
        $this->ensurePermission($request, 'finance.payments.reject');

        if ($payment->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // A rejection the payer cannot understand is a support ticket, so the
        // reason is required and has to say something.
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $payment = $this->verification->reject($payment, $validated['reason'], $request->user());

        return response()->json([
            'message' => 'Payment rejected.',
            'data' => $this->present($payment),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/fees/payments/{payment}/reverse",
     *     tags={"school-v2.5"},
     *     summary="Reverse a verified payment",
     *
     *     @OA\Response(response=200, description="Reversed"),
     *     @OA\Response(response=422, description="Not a verified payment")
     * )
     */
    public function reverse(Request $request, Payment $payment)
    {
        $this->ensurePermission($request, 'finance.payments.reverse');

        if ($payment->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $payment = $this->verification->reverse($payment, $validated['reason'], $request->user());

        return response()->json([
            'message' => 'Payment reversed.',
            'data' => $this->present($payment),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/fees/payments/{payment}/allocations",
     *     tags={"school-v2.5"},
     *     summary="Set which fees a verified payment covered",
     *     description="Replaces the whole allocation set. Does not change the student's balance, which follows the verified payment itself.",
     *
     *     @OA\Response(response=200, description="Allocated"),
     *     @OA\Response(response=422, description="Over-allocated or not verified")
     * )
     */
    public function allocate(Request $request, Payment $payment)
    {
        $this->ensurePermission($request, 'finance.payments.allocate');

        if ($payment->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $validated = $request->validate([
            'allocations' => 'present|array',
            'allocations.*.student_bill_item_id' => 'required|uuid',
            'allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        $payment = $this->allocations->allocate($payment, $validated['allocations'], $request->user());

        return response()->json([
            'message' => 'Payment allocated.',
            'data' => $this->present($payment),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/fees/payments/{payment}/evidence/{evidence}",
     *     tags={"school-v2.5"},
     *     summary="Download a piece of payment evidence",
     *     description="Streamed from a private disk after an ownership check; these files are never publicly addressable.",
     *
     *     @OA\Response(response=200, description="File streamed"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function evidence(Request $request, Payment $payment, PaymentEvidence $evidence): StreamedResponse
    {
        $this->ensurePermission($request, 'finance.payments.view');

        // Two checks, not one: the payment must be this school's, and the file
        // must be this payment's. Either alone would let an id from another
        // school's payment through.
        abort_if($payment->school_id !== $request->user()->school_id, 404);
        abort_if($evidence->payment_id !== $payment->id, 404);

        $disk = Storage::disk($evidence->disk);

        abort_unless($disk->exists($evidence->path), 404);

        return $disk->response(
            $evidence->path,
            $evidence->original_name,
            ['Content-Type' => $evidence->mime_type],
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/fees/payments/{payment}/receipt.pdf",
     *     tags={"school-v2.5"},
     *     summary="Download the PDF receipt for a verified payment",
     *
     *     @OA\Response(response=200, description="PDF streamed"),
     *     @OA\Response(response=422, description="Payment is not verified")
     * )
     */
    public function receipt(Request $request, Payment $payment)
    {
        $this->ensurePermission($request, 'finance.payments.view');

        if ($payment->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $content = $this->receipts->render($payment);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->receipts->filename($payment).'"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function present(Payment $payment): PaymentResource
    {
        $payment->load([
            'student:id,admission_no,first_name,last_name,school_class_id,class_arm_id',
            'student.school_class:id,name',
            'student.class_arm:id,name',
            'evidence',
            'session:id,name',
            'term:id,name',
            'bankDetail:id,bank_name,account_name',
            'verifier:id,name',
            'rejecter:id,name',
            'allocations.billItem:id,name',
        ]);

        return new PaymentResource($payment, true);
    }

    /**
     * @return School|\Illuminate\Http\JsonResponse
     */
    private function requireSchool(Request $request)
    {
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        return $school;
    }
}
