<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FinanceAuditLog;
use App\Models\School;
use App\Services\Fees\FinanceReportService;
use Illuminate\Http\Request;

/**
 * §20's dashboard metrics, §22's collections/outstanding reports, and §21's
 * audit trail.
 *
 * @OA\Tag(
 *     name="school-v2.5",
 *     description="Fees - assignments and bills"
 * )
 */
class FinanceReportController extends Controller
{
    public function __construct(private readonly FinanceReportService $reports) {}

    /**
     * @OA\Get(
     *     path="/api/v1/fees/overview",
     *     tags={"school-v2.5"},
     *     summary="Finance dashboard metrics",
     *     description="Total Expected Fees, Total Verified Payments, Pending Verification, Outstanding.",
     *
     *     @OA\Response(response=200, description="Metrics returned")
     * )
     */
    public function overview(Request $request)
    {
        $this->ensurePermission($request, 'finance.dashboard.view');

        $school = $this->requireSchool($request);
        if (! $school instanceof School) {
            return $school;
        }

        return response()->json([
            'data' => $this->reports->overview($school->id, $this->filters($request)),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/fees/reports/collections",
     *     tags={"school-v2.5"},
     *     summary="Verified collections by class",
     *
     *     @OA\Response(response=200, description="Report returned")
     * )
     */
    public function collections(Request $request)
    {
        $this->ensurePermission($request, 'finance.reports.view');

        $school = $this->requireSchool($request);
        if (! $school instanceof School) {
            return $school;
        }

        return response()->json([
            'data' => $this->reports->collectionsByClass($school->id, $this->filters($request)),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/fees/reports/outstanding",
     *     tags={"school-v2.5"},
     *     summary="Students with an outstanding balance",
     *
     *     @OA\Response(response=200, description="Report returned")
     * )
     */
    public function outstanding(Request $request)
    {
        $this->ensurePermission($request, 'finance.reports.view');

        $school = $this->requireSchool($request);
        if (! $school instanceof School) {
            return $school;
        }

        return response()->json([
            'data' => $this->reports->outstandingStudents($school->id, $this->filters($request)),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/fees/reports/outstanding.csv",
     *     tags={"school-v2.5"},
     *     summary="Outstanding-students report as CSV",
     *
     *     @OA\Response(response=200, description="CSV streamed")
     * )
     */
    public function outstandingCsv(Request $request)
    {
        $this->ensurePermission($request, 'finance.reports.view');

        $school = $this->requireSchool($request);
        if (! $school instanceof School) {
            return $school;
        }

        $rows = $this->reports->outstandingStudents($school->id, $this->filters($request));

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="outstanding-fees.csv"',
        ];

        return response()->stream(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // A UTF-8 BOM so Excel does not mangle a school's naira sign or
            // an accented name.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Admission No', 'Student', 'Class', 'Arm', 'Total', 'Paid', 'Outstanding', 'Status']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['admission_no'],
                    $row['student_name'],
                    $row['class_name'],
                    $row['class_arm_name'],
                    $row['total'],
                    $row['verified_paid'],
                    $row['outstanding'],
                    $row['status'],
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/fees/audit-logs",
     *     tags={"school-v2.5"},
     *     summary="Finance audit trail",
     *     description="Who did what to which financial record, and what it looked like before and after.",
     *
     *     @OA\Response(response=200, description="Audit log returned")
     * )
     */
    public function auditLogs(Request $request)
    {
        $this->ensurePermission($request, 'finance.audit.view');

        $school = $this->requireSchool($request);
        if (! $school instanceof School) {
            return $school;
        }

        $perPage = max((int) $request->input('per_page', 25), 1);

        $logs = FinanceAuditLog::query()
            ->where('school_id', $school->id)
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->input('subject_type')))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->input('subject_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($logs);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'session_id' => $request->input('session_id'),
            'term_id' => $request->input('term_id'),
            'school_class_id' => $request->input('school_class_id'),
            'class_arm_id' => $request->input('class_arm_id'),
            // fee_item_id and from/to apply to the totals endpoints
            // (overview, collections); status only means something on
            // outstandingStudents, the one report listing individual
            // students -- FinanceReportService ignores whichever of these
            // don't apply to a given method.
            'fee_item_id' => $request->input('fee_item_id'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'status' => $request->input('status'),
        ];
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
