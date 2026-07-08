<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Services\Teachers\TeacherAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherDashboardController extends Controller
{
    public function __construct(private TeacherAccessService $teacherAccess) {}

    /**
     * @OA\Get(
     *     path="/api/v1/staff/dashboard",
     *     tags={"school-v1.5"},
     *     summary="Staff dashboard (teacher view)",
     *     description="Returns teacher dashboard data for the authenticated staff member.",
     *
     *     @OA\Response(response=200, description="Dashboard returned"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
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
