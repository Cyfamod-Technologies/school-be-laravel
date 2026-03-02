<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\Admin\SchoolAdminService;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct(private readonly SchoolAdminService $schoolAdminService)
    {
    }

    public function summary()
    {
        return response()->json([
            'summary' => $this->schoolAdminService->getDashboardSummary(),
        ]);
    }

    public function schools(Request $request)
    {
        $schools = $this->schoolAdminService->listSchools(
            search: $request->query('search'),
            perPage: (int) $request->query('per_page', 12)
        );

        $payload = $schools->toArray();
        $payload['schools'] = $payload['data'] ?? [];

        return response()->json($payload);
    }

    public function showSchool(string $school)
    {
        $schoolModel = School::findOrFail($school);

        return response()->json([
            'school' => $this->schoolAdminService->getSchoolDetails($schoolModel),
        ]);
    }
}
