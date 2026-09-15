<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\StudentBillResource;
use App\Models\BankDetail;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentBill;
use App\Services\Fees\BillCalculator;
use App\Services\Fees\PaymentSubmissionService;
use App\Services\Fees\ReceiptRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The student and parent view of fees: what is owed, what has been paid, what
 * is still waiting on the school, and where to pay.
 *
 * Every figure comes from BillCalculator, the same one the admin screens read,
 * so the two can never disagree.
 *
 * @OA\Tag(
 *     name="student-portal",
 *     description="Student / parent portal"
 * )
 */
class StudentFeeController extends Controller
{
    public function __construct(
        private readonly BillCalculator $calculator,
        private readonly PaymentSubmissionService $submissions,
        private readonly ReceiptRenderer $receipts,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/student/fees/bill",
     *     tags={"student-portal"},
     *     summary="The student's current bill",
     *     description="Defaults to the school's current session and term.",
     *
     *     @OA\Response(response=200, description="Bill returned")
     * )
     */
    public function currentBill(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        $sessionId = $request->input('session_id', $student->current_session_id);
        $termId = $request->input('term_id', $student->current_term_id);

        $bill = StudentBill::query()
            ->where('student_id', $student->id)
            ->where('session_id', $sessionId)
            ->where('term_id', $termId)
            ->first();

        if (! $bill) {
            // Not an error: a term whose fees have not been assigned yet is a
            // normal state, and the portal should show an empty bill rather
            // than a failure.
            return response()->json([
                'data' => null,
                'totals' => $this->calculator->forStudentPeriod($student->id, $sessionId, $termId),
            ]);
        }

        return response()->json(['data' => $this->presentBill($bill)]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/student/fees/bills",
     *     tags={"student-portal"},
     *     summary="Every bill the student has had, newest first",
     *
     *     @OA\Response(response=200, description="Bills returned")
     * )
     */
    public function billHistory(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        $bills = StudentBill::query()
            ->where('student_id', $student->id)
            ->with(['session:id,name', 'term:id,name', 'schoolClass:id,name', 'classArm:id,name'])
            ->orderByDesc('created_at')
            ->get();

        $totals = $this->calculator->forBills($bills);

        return response()->json([
            'data' => $bills->map(
                fn (StudentBill $bill) => new StudentBillResource($bill, $totals[$bill->id] ?? null)
            ),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/student/fees/bills/{studentBill}",
     *     tags={"student-portal"},
     *     summary="One bill with its individual fees",
     *
     *     @OA\Response(response=200, description="Bill returned"),
     *     @OA\Response(response=404, description="Not the student's bill")
     * )
     */
    public function showBill(Request $request, StudentBill $studentBill): JsonResponse
    {
        $student = $this->resolveStudent($request);

        if ($studentBill->student_id !== $student->id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json(['data' => $this->presentBill($studentBill)]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/student/fees/payments",
     *     tags={"student-portal"},
     *     summary="Every payment on the student's account, with its status",
     *
     *     @OA\Response(response=200, description="Payments returned")
     * )
     */
    public function payments(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        $payments = Payment::query()
            ->where('student_id', $student->id)
            ->with([
                'evidence',
                'session:id,name',
                'term:id,name',
                'bankDetail:id,bank_name,account_name',
                'allocations.billItem:id,name',
            ])
            ->when($request->filled('session_id'), fn ($q) => $q->where('session_id', $request->input('session_id')))
            ->when($request->filled('term_id'), fn ($q) => $q->where('term_id', $request->input('term_id')))
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => PaymentResource::collection($payments),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/student/fees/payments/{payment}",
     *     tags={"student-portal"},
     *     summary="Full detail for one of the student's own payments",
     *     description="Spec §2/§5's 'Payment Details': the same figures Payment History lists, plus evidence and which fees it was applied to.",
     *
     *     @OA\Response(response=200, description="Payment returned"),
     *     @OA\Response(response=404, description="Not the student's payment")
     * )
     */
    public function showPayment(Request $request, Payment $payment): JsonResponse
    {
        $student = $this->resolveStudent($request);

        if ($payment->student_id !== $student->id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $payment->load([
            'evidence',
            'session:id,name',
            'term:id,name',
            'bankDetail:id,bank_name,account_name',
            'allocations.billItem:id,name',
        ]);

        return response()->json(['data' => new PaymentResource($payment)]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/student/fees/payment-accounts",
     *     tags={"student-portal"},
     *     summary="Where to pay: the school's active payment accounts",
     *
     *     @OA\Response(response=200, description="Accounts returned")
     * )
     */
    public function paymentAccounts(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        $accounts = BankDetail::query()
            ->where('school_id', $student->school_id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->get(['id', 'bank_name', 'account_name', 'account_number', 'branch', 'is_default']);

        return response()->json([
            'data' => $accounts,
            // What the school asks payers to put in the transfer narration, so
            // an unmatched payment can still be traced back to a student.
            'payment_reference' => trim($student->first_name.' '.$student->last_name).' / '.$student->admission_no,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/student/fees/payments",
     *     tags={"student-portal"},
     *     summary="Submit evidence of a payment already made",
     *     description="Creates a payment awaiting verification. It does not reduce the outstanding balance until an administrator verifies it.",
     *
     *     @OA\Response(response=201, description="Submitted"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function submitPayment(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'method' => ['required', 'string', Rule::in(Payment::METHODS)],
            // A payment cannot have been made in the future, and a date years
            // back is far more likely a typo than a real late submission.
            'paid_at' => 'required|date|before_or_equal:today|after:'.now()->subYears(2)->toDateString(),
            'session_id' => 'required|uuid',
            'term_id' => 'required|uuid',
            'payer_reference' => 'nullable|string|max:100',
            'bank_detail_id' => 'nullable|uuid',
            'note' => 'nullable|string|max:1000',
            'evidence' => 'required|array|min:1|max:3',
            'evidence.*' => 'file|max:5120',
        ]);

        $payment = $this->submissions->submit(
            $student,
            $validated,
            $request->file('evidence', []),
            $student,
        );

        return response()->json([
            'message' => 'Payment submitted. It will show as paid once the school has verified it.',
            'data' => new PaymentResource($payment->load('evidence')),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/student/fees/payments/{payment}/receipt.pdf",
     *     tags={"student-portal"},
     *     summary="Download the receipt for one of the student's own verified payments",
     *
     *     @OA\Response(response=200, description="PDF streamed"),
     *     @OA\Response(response=404, description="Not the student's payment"),
     *     @OA\Response(response=422, description="Payment is not verified")
     * )
     */
    public function receipt(Request $request, Payment $payment)
    {
        $student = $this->resolveStudent($request);

        if ($payment->student_id !== $student->id) {
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

    private function presentBill(StudentBill $bill): StudentBillResource
    {
        $bill->load([
            'session:id,name',
            'term:id,name',
            'schoolClass:id,name',
            'classArm:id,name',
            'items',
        ]);

        return new StudentBillResource(
            $bill,
            $this->calculator->forBill($bill),
            $this->calculator->paidPerItem($bill->id),
        );
    }

    private function resolveStudent(Request $request): Student
    {
        $student = $request->user('student');

        if ($student instanceof Student) {
            return $student;
        }

        $student = $request->user();

        if ($student instanceof Student) {
            return $student;
        }

        abort(401, 'Unauthenticated.');
    }
}
