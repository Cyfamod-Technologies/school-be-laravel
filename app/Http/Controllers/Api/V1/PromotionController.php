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

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $service)
    {
    }

    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'current_session_id' => ['nullable', 'uuid'],
            'current_term_id' => ['nullable', 'uuid'],
            'current_class_id' => ['nullable', 'uuid'],
            'current_class_arm_id' => ['nullable', 'uuid'],
            'current_section_id' => ['nullable', 'uuid'],
            'target_session_id' => ['required', 'uuid'],
            'target_class_id' => ['required', 'uuid'],
            'target_class_arm_id' => ['nullable', 'uuid'],
            'target_section_id' => ['nullable', 'uuid'],
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
