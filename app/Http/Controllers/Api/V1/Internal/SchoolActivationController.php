<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Mail\WebsiteLive;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        if ($school->email) {
            try {
                Mail::to($school->email)->send(new WebsiteLive($school));
            } catch (\Throwable $e) {
                // The activation itself already succeeded and must not be
                // rolled back over a notification failure -- log and move
                // on, same as any other best-effort notification.
                Log::warning("[SchoolActivationController] Failed to email {$school->name} that their website is live: {$e->getMessage()}");
            }
        }

        return response()->json(['activated' => true]);
    }
}
