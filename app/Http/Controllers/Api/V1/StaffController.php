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
use Spatie\Permission\PermissionRegistrar;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'staff.view');

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
        $this->ensurePermission($request, 'staff.create');

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
        $systemRole = $this->determineSystemRole($validated['role']);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Hash::make($temporaryPassword),
            'role' => $systemRole,
            'phone' => $validated['phone'],
        ]);

        $primaryRole = $this->resolveRoleModel($school->id, $systemRole);
        $staffRole = $this->resolveRoleModel($school->id, 'staff');

        $this->withTeamContext($school->id, function () use ($user, $primaryRole, $staffRole, $systemRole) {
            $roles = [$primaryRole];

            if ($systemRole !== 'staff') {
                $roles[] = $staffRole;
            }

            $user->syncRoles(collect($roles)->unique('id'));
        });

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
        $this->ensurePermission($request, 'staff.view');
        $this->authorizeStaffAccess($request, $staff);

        return response()->json([
            'data' => $staff->load(['user.roles' => function ($query) {
                $query->where('guard_name', config('permission.default_guard', 'sanctum'));
            }]),
        ]);
    }

    public function update(Request $request, Staff $staff)
    {
        $this->ensurePermission($request, 'staff.update');
        $this->authorizeStaffAccess($request, $staff);
        $staff->loadMissing('user');

        foreach (['full_name', 'email', 'phone', 'role', 'gender', 'address', 'qualifications', 'employment_start_date'] as $field) {
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

        $systemRole = array_key_exists('role', $validated)
            ? $this->determineSystemRole($validated['role'])
            : null;

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

        if ($systemRole) {
            $staff->user->forceFill(['role' => $systemRole])->save();

            $primaryRole = $this->resolveRoleModel($staff->school_id, $systemRole);
            $staffRole = $this->resolveRoleModel($staff->school_id, 'staff');

            $this->withTeamContext($staff->school_id, function () use ($staff, $primaryRole, $staffRole, $systemRole) {
                $roles = [$primaryRole];

                if ($systemRole !== 'staff') {
                    $roles[] = $staffRole;
                }

                $staff->user->syncRoles(collect($roles)->unique('id'));
            });
        }

        return response()->json([
            'data' => $staff->fresh()->load('user.roles'),
        ]);
    }

    public function destroy(Request $request, Staff $staff)
    {
        $this->ensurePermission($request, 'staff.delete');
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

    private function determineSystemRole(?string $label): string
    {
        $normalized = Str::of((string) $label)->lower();

        if ($normalized->contains('teach')) {
            return 'teacher';
        }

        if ($normalized->contains('account')) {
            return 'accountant';
        }

        return 'staff';
    }

    private function resolveRoleModel(string $schoolId, string $roleName): Role
    {
        $guard = config('permission.default_guard', 'sanctum');

        $description = match ($roleName) {
            'teacher' => 'Teacher',
            'accountant' => 'Accountant',
            'staff' => 'School staff',
            default => Str::headline($roleName),
        };

        return Role::query()->updateOrCreate(
            [
                'name' => $roleName,
                'school_id' => $schoolId,
            ],
            [
                'guard_name' => $guard,
                'description' => $description,
            ]
        );
    }

    /**
     * Execute a callback with the Spatie permission team context scoped to the given school.
     *
     * @template TReturn
     *
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    private function withTeamContext(string $schoolId, callable $callback)
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = method_exists($registrar, 'getPermissionsTeamId')
            ? $registrar->getPermissionsTeamId()
            : null;

        $registrar->setPermissionsTeamId($schoolId);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previousTeam);
        }
    }
}
