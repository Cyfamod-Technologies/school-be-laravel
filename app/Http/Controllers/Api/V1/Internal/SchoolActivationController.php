<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\JsonResponse;

/**
 * Called only by sms-enterprise-edition (server-to-server, guarded by
 * VerifyInternalSecret), once its Vercel domain automation succeeds. This
 * is the sole handoff point between the two systems -- this backend still
 * owns and enforces the actual `activated` gate itself, in
 * PublicSchoolWebsiteController.
 */
class SchoolActivationController extends Controller
{
    public function activate(string $schoolId): JsonResponse
    {
        $school = School::query()->findOrFail($schoolId);

        $school->activated = true;
        $school->save();

        return response()->json(['activated' => true]);
    }
}
