<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentBillResource;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentBill;
use App\Services\Fees\BillCalculator;
use App\Services\Fees\BillGenerationService;
use Illuminate\Http\Request;

/**
 * The admin view of what students owe.
 *
 * Every figure here comes from BillCalculator, so this and the student portal
 * can never disagree about a balance.
 *
 * @OA\Tag(
 *     name="school-v2.5",
 *     description="Fees - assignments and bills"
 * )
 */
class StudentBillController extends Controller
{
    public function __construct(
        private readonly BillCalculator $calculator,
        private readonly BillGenerationService $bills,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/fees/bills",
     *     tags={"school-v2.5"},
     *     summary="List student bills with balances",
     *     description="Filter by session, term, class, arm, payment status or student name.",
     *
     *     @OA\Response(response=200, description="Bills returned")
     * )
     */
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'finance.bills.view');

        $school = $this->requireSchool($request);
        if (! $school instanceof School) {
            return $school;
        }

        $perPage = max((int) $request->input('per_page', 15), 1);

        $query = StudentBill::query()
            ->where('school_id', $school->id)
            ->with(['student:id,admission_no,first_name,last_name', 'schoolClass:id,name', 'classArm:id,name', 'session:id,name', 'term:id,name'])
            ->when($request->filled('session_id'), fn ($q) => $q->where('session_id', $request->input('session_id')))
            ->when($request->filled('term_id'), fn ($q) => $q->where('term_id', $request->input('term_id')))
            ->when($request->filled('school_class_id'), fn ($q) => $q->where('school_class_id', $request->input('school_class_id')))
            ->when($request->filled('class_arm_id'), fn ($q) => $q->where('class_arm_id', $request->input('class_arm_id')))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('student', function ($student) use ($request) {
                $search = $request->input('search');
                $student->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('admission_no', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at');

        $status = $request->input('status');

        if ($status && in_array($status, BillCalculator::STATUSES, true)) {
            return $this->paginateByStatus($query, $status, $perPage, $request);
        }

        $bills = $query->paginate($perPage)->withQueryString();
        $totals = $this->calculator->forBills($bills->getCollection());

        $bills->setCollection($bills->getCollection()->map(
            fn (StudentBill $bill) => new StudentBillResource($bill, $totals[$bill->id] ?? null)
        ));

        return response()->json($bills);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/fees/bills/{studentBill}",
     *     tags={"school-v2.5"},
     *     summary="Get one bill with its line items",
     *
     *     @OA\Response(response=200, description="Bill returned"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, StudentBill $studentBill)
    {
        $this->ensurePermission($request, 'finance.bills.view');

        if ($studentBill->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return $this->respondWithBill($studentBill);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/fees/students/{student}/bill",
     *     tags={"school-v2.5"},
     *     summary="Get a student's bill for a session and term",
     *     description="Falls back to the school's current session and term.",
     *
     *     @OA\Response(response=200, description="Bill returned"),
     *     @OA\Response(response=404, description="No bill for that period")
     * )
     */
    public function forStudent(Request $request, Student $student)
    {
        $this->ensurePermission($request, 'finance.bills.view');

        $school = $this->requireSchool($request);
        if (! $school instanceof School) {
            return $school;
        }

        if ($student->school_id !== $school->id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $sessionId = $request->input('session_id', $school->current_session_id);
        $termId = $request->input('term_id', $school->current_term_id);

        $bill = StudentBill::query()
            ->where('student_id', $student->id)
            ->where('session_id', $sessionId)
            ->where('term_id', $termId)
            ->first();

        if (! $bill) {
            return response()->json([
                'message' => 'This student has no bill for the selected session and term.',
            ], 404);
        }

        return $this->respondWithBill($bill);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/fees/bills/generate",
     *     tags={"school-v2.5"},
     *     summary="Generate or refresh bills for a session and term",
     *     description="Idempotent: safe to run repeatedly.",
     *
     *     @OA\Response(response=200, description="Generation summary")
     * )
     */
    public function generate(Request $request)
    {
        $this->ensurePermission($request, 'finance.bills.generate');

        $school = $this->requireSchool($request);
        if (! $school instanceof School) {
            return $school;
        }

        $validated = $request->validate([
            'session_id' => 'required|uuid',
            'term_id' => 'required|uuid',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'uuid',
        ]);

        $summary = $this->bills->generateForSchool(
            $school->id,
            $validated['session_id'],
            $validated['term_id'],
            $validated['student_ids'] ?? null,
            $request->user(),
        );

        return response()->json([
            'message' => 'Bills generated.',
            'data' => $summary,
        ]);
    }

    /**
     * Status is derived from payments, not stored, so it cannot be filtered in
     * SQL. Resolve it over the matching bills and paginate the result by hand.
     */
    private function paginateByStatus($query, string $status, int $perPage, Request $request)
    {
        $bills = $query->get();
        $totals = $this->calculator->forBills($bills);

        $matching = $bills->filter(
            fn (StudentBill $bill) => ($totals[$bill->id]['status'] ?? null) === $status
        )->values();

        $page = max((int) $request->input('page', 1), 1);

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $matching->forPage($page, $perPage)->values()->map(
                fn (StudentBill $bill) => new StudentBillResource($bill, $totals[$bill->id] ?? null)
            ),
            $matching->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return response()->json($paginator);
    }

    private function respondWithBill(StudentBill $bill)
    {
        $bill->load([
            'student:id,admission_no,first_name,last_name',
            'schoolClass:id,name',
            'classArm:id,name',
            'session:id,name',
            'term:id,name',
            'items',
        ]);

        return response()->json([
            'data' => new StudentBillResource(
                $bill,
                $this->calculator->forBill($bill),
                $this->calculator->paidPerItem($bill->id),
            ),
        ]);
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
