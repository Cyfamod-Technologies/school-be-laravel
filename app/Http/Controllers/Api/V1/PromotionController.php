<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PromotionLog;
use App\Models\Student;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="school-v2.0",
 *     description="v2.0 – Rollover, Promotions, Attendance, Fees, Roles"
 * )
 */
class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $service)
    {
    }

    /**
     * @OA\Post(
     *     path="/api/v1/promotions/bulk",
     *     tags={"school-v2.0"},
     *     summary="Bulk promote students",
     *     description="Promotes students from a source class/session/term to a target class/session (optionally retaining subjects).",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"target_session_id","target_class_id","student_ids"},
     *             @OA\Property(property="current_session_id", type="string", format="uuid", nullable=true),
     *             @OA\Property(property="current_term_id", type="string", format="uuid", nullable=true),
     *             @OA\Property(property="current_class_id", type="string", format="uuid", nullable=true),
     *             @OA\Property(property="current_class_arm_id", type="string", format="uuid", nullable=true),
     *             @OA\Property(property="current_section_id", type="string", format="uuid", nullable=true),
     *             @OA\Property(property="target_session_id", type="string", format="uuid"),
     *             @OA\Property(property="target_class_id", type="string", format="uuid"),
     *             @OA\Property(property="target_class_arm_id", type="string", format="uuid", nullable=true),
     *             @OA\Property(property="target_section_id", type="string", format="uuid", nullable=true),
     *             @OA\Property(property="retain_subjects", type="boolean", example=false),
     *             @OA\Property(property="student_ids", type="array", @OA\Items(type="string", format="uuid"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Promotion completed"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'current_session_id' => ['nullable', 'uuid'],
            'current_term_id' => ['nullable', 'uuid'],
            'current_class_id' => ['nullable', 'uuid'],
            'current_class_arm_id' => ['nullable', 'uuid'],
            'target_session_id' => ['required', 'uuid'],
            'target_class_id' => ['required', 'uuid'],
            'target_class_arm_id' => ['nullable', 'uuid'],
            'retain_subjects' => ['sometimes', 'boolean'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['uuid'],
        ]);

        $user = $request->user();

        $students = Student::query()
            ->whereIn('id', $validated['student_ids'])
            ->where('school_id', $user->school_id)
            ->get();

        if ($students->count() !== count($validated['student_ids'])) {
            return response()->json([
                'message' => 'One or more students could not be found in your school.',
            ], 422);
        }

        $results = $this->service->promoteStudents($validated['student_ids'], $validated, $user->id);

        return response()->json([
            'message' => 'Students promoted successfully.',
            'data' => $results,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/promotions/history",
     *     tags={"school-v2.0"},
     *     summary="Promotion history",
     *     description="Returns recent promotion logs with optional filters.",
     *     @OA\Parameter(name="session_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="term_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="school_class_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="History returned")
     * )
     */
    public function history(Request $request)
    {
        $user = $request->user();
        $query = PromotionLog::query()
            ->with([
                'student:id,first_name,last_name,admission_no,school_id',
                'fromClass:id,name',
                'toClass:id,name',
                'performer:id,name',
                'toSession:id,name',
            ])
            ->whereHas('student', fn ($q) => $q->where('school_id', $user->school_id));

        if ($request->filled('session_id')) {
            $query->where('to_session_id', $request->string('session_id'));
        }

        if ($request->filled('term_id')) {
            $query->whereJsonContains('meta->term_id', $request->string('term_id'));
        }

        if ($request->filled('school_class_id')) {
            $query->where('to_class_id', $request->string('school_class_id'));
        }

        $logs = $query->orderByDesc('promoted_at')->limit(500)->get()->map(fn (PromotionLog $log) => $this->transformLog($log));

        return response()->json(['data' => $logs]);
    }

    public function exportPdf(Request $request)
    {
        $content = "Promotion report export is not yet implemented.";
        return Response::make($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="promotion-report.pdf"',
        ]);
    }

    private function transformLog(PromotionLog $log): array
    {
        $student = $log->student;
        $fromClass = $log->fromClass;
        $toClass = $log->toClass;
        $performer = $log->performer;

        return [
            'id' => $log->id,
            'student_id' => $log->student_id,
            'student_name' => $student ? trim($student->first_name . ' ' . $student->last_name) : null,
            'from_class' => $fromClass?->name,
            'to_class' => $toClass?->name,
            'performed_by' => $performer?->name,
            'promoted_at' => optional($log->promoted_at)->toISOString(),
        ];
    }
}
