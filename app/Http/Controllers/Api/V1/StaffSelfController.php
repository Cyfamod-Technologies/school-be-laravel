<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffSelfController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'profile.view');

        $staff = $this->resolveStaff($request)->loadMissing('user');

        return response()->json([
            'data' => new StaffResource($staff),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'profile.edit');

        $staff = $this->resolveStaff($request)->loadMissing('user');

        foreach (['full_name', 'email', 'phone', 'address', 'qualifications'] as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $validated = $request->validate([
            'full_name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($staff->user_id),
            ],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('staff', 'phone')->where(fn ($q) => $q->where('school_id', $staff->school_id))->ignore($staff->id),
            ],
            'address' => 'nullable|string|max:255',
            'qualifications' => 'nullable|string|max:255',
            'gender' => ['nullable', Rule::in(['male', 'female', 'others'])],
            'employment_start_date' => 'nullable|date',
            'password' => 'nullable|string|min:8|confirmed',
            'old_password' => 'required_with:password|string',
        ]);

        $staffUpdates = [];
        $userUpdates = [];

        if (array_key_exists('full_name', $validated)) {
            $staffUpdates['full_name'] = $validated['full_name'];
            $userUpdates['name'] = $validated['full_name'];
        }

        if (array_key_exists('email', $validated)) {
            $staffUpdates['email'] = $validated['email'];
            $userUpdates['email'] = $validated['email'];
        }

        if (array_key_exists('phone', $validated)) {
            $staffUpdates['phone'] = $validated['phone'];
            $userUpdates['phone'] = $validated['phone'];
        }

        if (array_key_exists('address', $validated)) {
            $staffUpdates['address'] = $validated['address'];
        }

        if (array_key_exists('qualifications', $validated)) {
            $staffUpdates['qualifications'] = $validated['qualifications'];
        }

        if (array_key_exists('gender', $validated)) {
            $staffUpdates['gender'] = strtolower($validated['gender']);
        }

        if (array_key_exists('employment_start_date', $validated)) {
            $staffUpdates['employment_start_date'] = $validated['employment_start_date'];
        }

        if ($staffUpdates) {
            $staff->update($staffUpdates);
        }

        if ($userUpdates) {
            $staff->user->update($userUpdates);
        }

        if (! empty($validated['password'])) {
            $this->ensurePermission($request, 'profile.password');

            if (! Hash::check($validated['old_password'], $staff->user->password)) {
                return response()->json([
                    'message' => 'Old password is incorrect.',
                ], 422);
            }

            $staff->user->forceFill([
                'password' => Hash::make($validated['password']),
            ])->save();
        }

        return response()->json([
            'data' => new StaffResource($staff->fresh()->load('user')),
        ]);
    }

    private function resolveStaff(Request $request): Staff
    {
        $staff = $request->user()->staff;

        abort_if(! $staff, 404, 'Staff profile not found for this user.');
        abort_if($staff->school_id !== $request->user()->school_id, 404, 'Staff profile mismatch.');

        return $staff;
    }
}
