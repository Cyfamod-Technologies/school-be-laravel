<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Session;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StudentPortalAttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        $validated = $request->validate([
            'session_id' => ['required', 'uuid'],
            'term_id' => ['required', 'uuid'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $session = Session::query()
            ->whereKey($validated['session_id'])
            ->where('school_id', $student->school_id)
            ->firstOrFail();

        $term = Term::query()
            ->whereKey($validated['term_id'])
            ->where('school_id', $student->school_id)
            ->where('session_id', $session->id)
            ->firstOrFail();

        $query = Attendance::query()
            ->where('student_id', $student->id)
            ->where('session_id', $session->id)
            ->where('term_id', $term->id);

        $statusBreakdown = (clone $query)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $present = (int) ($statusBreakdown['present'] ?? 0);
        $absent = (int) ($statusBreakdown['absent'] ?? 0);
        $late = (int) ($statusBreakdown['late'] ?? 0);
        $excused = (int) ($statusBreakdown['excused'] ?? 0);
        $recordedDays = $present + $absent + $late + $excused;

        $month = $validated['month'] ?? now()->format('Y-m');
        $monthStart = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $days = (clone $query)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('date')
            ->get(['id', 'date', 'status', 'updated_at'])
            ->map(fn (Attendance $attendance) => [
                'id' => $attendance->id,
                'date' => $attendance->date?->toDateString(),
                'status' => $attendance->status,
                'updated_at' => optional($attendance->updated_at)->toISOString(),
            ]);

        return response()->json([
            'session' => [
                'id' => $session->id,
                'name' => $session->name,
                'start_date' => optional($session->start_date)->toDateString(),
                'end_date' => optional($session->end_date)->toDateString(),
            ],
            'term' => [
                'id' => $term->id,
                'name' => $term->name,
                'start_date' => optional($term->start_date)->toDateString(),
                'end_date' => optional($term->end_date)->toDateString(),
            ],
            'month' => $month,
            'summary' => [
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'excused' => $excused,
                'recorded_days' => $recordedDays,
                'percentage' => $recordedDays > 0
                    ? round((($present + $late) / $recordedDays) * 100, 2)
                    : 0,
            ],
            'days' => $days,
        ]);
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
