<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max((int) $request->input('per_page', 10), 1);
        $sortBy = $request->input('sortBy', 'full_name');
        $sortDirection = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['full_name', 'email', 'phone', 'role', 'created_at'];

        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'full_name';
        }

        $query = Staff::with(['user.roles' => function ($query) {
                $query->where('guard_name', config('permission.default_guard', 'sanctum'));
            }])
            ->where('school_id', $request->user()->school_id)
            ->when($request->filled('role'), function ($q) use ($request) {
                $q->where('role', $request->input('role'));
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($inner) use ($search) {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDirection);

        $staff = $query->paginate($perPage)->withQueryString();

        return response()->json($staff);
    }

    public function store(Request $request)
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('staff', 'phone')->where(fn ($q) => $q->where('school_id', $school->id)),
            ],
            'role' => 'required|string|max:255',
            'gender' => ['required', Rule::in(['male', 'female', 'others'])],
            'address' => 'nullable|string|max:255',
            'qualifications' => 'nullable|string|max:255',
            'employment_start_date' => 'nullable|date',
            'photo' => 'nullable|image|max:2048',
        ]);

        $temporaryPassword = '123456';

        $user = User::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Hash::make($temporaryPassword),
            'role' => 'staff',
            'phone' => $validated['phone'],
        ]);

        $staffRole = Role::query()->updateOrCreate(
            [
                'name' => 'staff',
                'school_id' => $school->id,
            ],
            [
                'guard_name' => config('permission.default_guard', 'sanctum'),
                'description' => 'School staff',
            ]
        );

        if (! $user->hasRole($staffRole)) {
            $user->assignRole($staffRole);
        }

        $photoUrl = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('staff-photos', 'public');
            $photoUrl = Storage::url($path);
        }

        $staff = Staff::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'user_id' => $user->id,
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => $validated['role'],
            'gender' => strtolower($validated['gender']),
            'address' => $validated['address'] ?? null,
            'qualifications' => $validated['qualifications'] ?? null,
            'employment_start_date' => $validated['employment_start_date'] ?? null,
            'photo_url' => $photoUrl,
        ]);

        return response()->json([
            'data' => $staff->load(['user.roles' => function ($query) {
                $query->where('guard_name', config('permission.default_guard', 'sanctum'));
            }]),
            'temporary_password' => $temporaryPassword,
        ], 201);
    }

    public function show(Request $request, Staff $staff)
    {
        $this->authorizeStaffAccess($request, $staff);

        return response()->json([
            'data' => $staff->load(['user.roles' => function ($query) {
                $query->where('guard_name', config('permission.default_guard', 'sanctum'));
            }]),
        ]);
    }

    public function update(Request $request, Staff $staff)
    {
        $this->authorizeStaffAccess($request, $staff);

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
            'role' => 'sometimes|required|string|max:255',
            'gender' => ['sometimes', 'required', Rule::in(['male', 'female', 'others'])],
            'address' => 'nullable|string|max:255',
            'qualifications' => 'nullable|string|max:255',
            'employment_start_date' => 'nullable|date',
            'photo' => 'nullable|image|max:2048',
        ]);

        $staffUpdates = [];

        if (array_key_exists('full_name', $validated)) {
            $staffUpdates['full_name'] = $validated['full_name'];
        }
        if (array_key_exists('email', $validated)) {
            $staffUpdates['email'] = $validated['email'];
        }
        if (array_key_exists('phone', $validated)) {
            $staffUpdates['phone'] = $validated['phone'];
        }
        if (array_key_exists('role', $validated)) {
            $staffUpdates['role'] = $validated['role'];
        }
        if (array_key_exists('gender', $validated)) {
            $staffUpdates['gender'] = strtolower($validated['gender']);
        }
        if (array_key_exists('address', $validated)) {
            $staffUpdates['address'] = $validated['address'];
        }
        if (array_key_exists('qualifications', $validated)) {
            $staffUpdates['qualifications'] = $validated['qualifications'];
        }
        if (array_key_exists('employment_start_date', $validated)) {
            $staffUpdates['employment_start_date'] = $validated['employment_start_date'];
        }

        if ($request->hasFile('photo')) {
            if ($staff->photo_url) {
                $previousPath = str_replace('/storage/', '', $staff->photo_url);
                Storage::disk('public')->delete($previousPath);
            }

            $path = $request->file('photo')->store('staff-photos', 'public');
            $staffUpdates['photo_url'] = Storage::url($path);
        }

        if (! empty($staffUpdates)) {
            $staff->update($staffUpdates);
        }

        $userUpdates = [];
        if (array_key_exists('full_name', $validated)) {
            $userUpdates['name'] = $validated['full_name'];
        }
        if (array_key_exists('email', $validated)) {
            $userUpdates['email'] = $validated['email'];
        }
        if (array_key_exists('phone', $validated)) {
            $userUpdates['phone'] = $validated['phone'];
        }

        if (! empty($userUpdates)) {
            $staff->user->update($userUpdates);
        }

        return response()->json([
            'data' => $staff->fresh()->load('user.roles'),
        ]);
    }

    public function destroy(Request $request, Staff $staff)
    {
        $this->authorizeStaffAccess($request, $staff);

        $user = $staff->user;

        if ($staff->photo_url) {
            $previousPath = str_replace('/storage/', '', $staff->photo_url);
            Storage::disk('public')->delete($previousPath);
        }

        $staff->delete();

        if ($user) {
            $user->delete();
        }

        return response()->json(null, 204);
    }

    protected function authorizeStaffAccess(Request $request, Staff $staff): void
    {
        abort_unless($staff->school_id === $request->user()->school_id, 404);
    }
}
