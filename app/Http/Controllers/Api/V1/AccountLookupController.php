<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configuredKey = (string) config('services.account_lookup.key');
        $providedKey = (string) $request->header('X-Account-Lookup-Key');

        if ($configuredKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'account_type' => ['required', Rule::in(['staff', 'student'])],
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $identifier = trim($validated['identifier']);

        $found = $validated['account_type'] === 'student'
            ? Student::query()->where('admission_no', $identifier)->exists()
            : User::query()->where('email', mb_strtolower($identifier))->exists();

        return response()->json(['found' => $found]);
    }
}
