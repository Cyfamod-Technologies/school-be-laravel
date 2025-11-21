<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Services\Teachers\TeacherAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherDashboardController extends Controller
{
    public function __construct(private TeacherAccessService $teacherAccess)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $scope = $this->teacherAccess->forUser($request->user());

        if (! $scope->isTeacher()) {
            abort(403, 'Only staff assigned as teachers can access this dashboard.');
        }

        $staff = $scope->staff();

        if (! $staff) {
            abort(404, 'Staff profile not found for this account.');
        }

        $assignments = $scope->summarizeAssignments();
        $subjectCount = $scope->subjectAssignments()
            ->pluck('subject_id')
            ->filter()
            ->unique()
            ->count();

        return response()->json([
            'teacher' => new StaffResource($staff->loadMissing('user')),
            'assignments' => $assignments,
            'stats' => [
                'classes' => $assignments->count(),
                'subjects' => $subjectCount,
            ],
        ]);
    }
}
