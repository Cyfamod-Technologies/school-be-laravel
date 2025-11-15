<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Mail\VerifyEmail;
use App\Models\EmailVerificationToken;
use App\Models\School;
use App\Models\Session;
use App\Models\Term;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Student;
use App\Models\SchoolParent;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\Rbac\RbacService;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="School API",
 *      description="API for managing school data"
 * )
 */
class SchoolController extends Controller
{
    /**
     * @OA\Post(
     *      path="/v1/register-school",
     *      summary="Register a new school",
     *      tags={"school-v1.0"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name","address","email","password","password_confirmation"},
     *              @OA\Property(property="name", type="string", example="My School"),
     *              @OA\Property(property="address", type="string", example="123 Main St"),
     *              @OA\Property(property="email", type="string", format="email", example="school@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="password"),
     *              @OA\Property(property="password_confirmation", type="string", format="password", example="password"),
     *              @OA\Property(property="subdomain", type="string", example="my-school")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="School registered successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="School registered successfully"),
     *              @OA\Property(property="school", type="object",
     *                  @OA\Property(property="id", type="string", format="uuid"),
     *                  @OA\Property(property="name", type="string"),
     *                  @OA\Property(property="slug", type="string"),
     *                  @OA\Property(property="address", type="string"),
     *                  @OA\Property(property="email", type="string", format="email"),
     *                  @OA\Property(property="phone", type="string"),
     *                  @OA\Property(property="logo_url", type="string"),
     *                  @OA\Property(property="established_at", type="string", format="date"),
     *                  @OA\Property(property="owner_name", type="string"),
     *                  @OA\Property(property="status", type="string", enum={"active", "inactive"}),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time")
     *              ),
     *              @OA\Property(property="user", type="object",
     *                  @OA\Property(property="id", type="string", format="uuid"),
     *                  @OA\Property(property="name", type="string"),
     *                  @OA\Property(property="email", type="string", format="email"),
     *                  @OA\Property(property="role", type="string", enum={"staff", "parent", "super_admin", "accountant"}),
     *                  @OA\Property(property="status", type="string", enum={"active", "inactive", "suspended"}),
     *                  @OA\Property(property="last_login", type="string", format="date-time"),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error"
     *      )
     * )
     */
    public function register(Request $request, RbacService $rbacService)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'email' => 'required|string|email|max:255|unique:schools',
            'password' => 'required|string|min:8|confirmed',
            'subdomain' => 'required|string|max:255|unique:schools',
        ]);

        [$school, $user] = DB::transaction(function () use ($validatedData, $rbacService) {
            $acronym = $this->generateSchoolAcronym($validatedData['name']);
            $nextCode = (int) School::query()->lockForUpdate()->max('code_sequence');
            $nextCode = $nextCode > 0 ? $nextCode + 1 : 1;
            $slug = $this->generateUniqueSchoolSlug($validatedData['name']);

            $school = School::create([
                'id' => Str::uuid(),
                'name' => $validatedData['name'],
                'acronym' => $acronym,
                'code_sequence' => $nextCode,
                'slug' => $slug,
                'subdomain' => $validatedData['subdomain'],
                'address' => $validatedData['address'],
                'email' => $validatedData['email'],
                'phone' => '1234567890', // Add a dummy phone number
            ]);

            $user = User::create([
                'id' => Str::uuid(),
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'role' => 'admin',
                'school_id' => $school->id,
                'status' => 'active',
            ]);

            $rbacService->bootstrapForSchool($school, $user);
            $user->load('roles');

            return [$school, $user];
        });

        $this->handleEmailVerification($user);

        return response()->json([
            'message' => 'School registered successfully.',
            'school' => $school,
            'user' => $user,
            'verification_required' => config('features.email_verification'),
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/v1/login",
     *     summary="Login as a school admin",
     *     tags={"school-v1.0"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful login",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="user", type="object"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are not correct.'],
            ]);
        }

        if (config('features.email_verification') && ! $user->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => ['Please verify your email address before logging in.'],
            ]);
        }

        $rbac = app(RbacService::class);

        if (in_array($user->role, ['admin', 'super_admin'], true) && $user->school) {
            $guard = config('permission.default_guard', 'sanctum');
            $hasSchoolRole = $this->withTeamContext($user->school_id, function () use ($user, $guard) {
                return $user->roles()
                    ->where('roles.guard_name', $guard)
                    ->where(function ($query) use ($user) {
                        return $query
                            ->whereNull('roles.school_id')
                            ->orWhere('roles.school_id', $user->school_id);
                    })
                    ->exists();
            });

            if (! $hasSchoolRole) {
                $rbac->bootstrapForSchool($user->school, $user);
            }
        }

        $hasAllowedRole = $this->withTeamContext($user->school_id, function () use ($user) {
            $guard = config('permission.default_guard', 'sanctum');
            return $user->roles()
                ->where('roles.guard_name', $guard)
                ->where(function ($query) use ($user) {
                    return $query
                        ->whereNull('roles.school_id')
                        ->orWhere('roles.school_id', $user->school_id);
                })
                ->exists();
        });

        if (! $hasAllowedRole) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->school) {
            $this->withTeamContext($user->school_id, function () use ($user, $rbac) {
                $rbac->syncCorePermissions($user->school);
                $rbac->ensureOperationalRoles($user->school);

                if ($user->hasRole('admin')) {
                    $rbac->syncAdminPermissions($user->school);
                }

                if ($user->hasRole('super_admin')) {
                    $rbac->syncSuperAdminPermissions($user->school);
                }
            });
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/v1/logout",
     *     summary="Logout the authenticated user",
     *     tags={"school-v1.0"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
    /**
     * @OA\Put(
     *     path="/v1/school",
     *     summary="Update school profile",
     *     tags={"school-v1.0"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="logo_url", type="string"),
     *                 @OA\Property(property="established_at", type="string", format="date"),
     *                 @OA\Property(property="owner_name", type="string"),
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="School profile updated successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateSchoolProfile(Request $request)
    {
        $user = Auth::user();
        $school = $user->school;

        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'address' => 'string',
            'email' => 'string|email|max:255',
            'phone' => 'string|max:50',
            'logo_url' => 'string|max:512',
            'signature_url' => 'string|max:512',
            'logo' => 'nullable|image|max:4096',
            'signature' => 'nullable|image|max:4096',
            'established_at' => 'date',
            'owner_name' => 'string|max:255',
            'current_session_id' => 'nullable|uuid',
            'current_term_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('schools/logos', 'public');
            if (! empty($school->logo_url)) {
                $this->deletePublicFile($school->logo_url);
            }
            $data['logo_url'] = $this->formatStoredFileUrl($logoPath);
        } elseif (array_key_exists('logo_url', $data) && ! $data['logo_url']) {
            if (! empty($school->logo_url)) {
                $this->deletePublicFile($school->logo_url);
            }
            $data['logo_url'] = null;
        }

        if ($request->hasFile('signature')) {
            $signaturePath = $request->file('signature')->store('schools/signatures', 'public');
            if (! empty($school->signature_url)) {
                $this->deletePublicFile($school->signature_url);
            }
            $data['signature_url'] = $this->formatStoredFileUrl($signaturePath);
        } elseif (array_key_exists('signature_url', $data) && ! $data['signature_url']) {
            if (! empty($school->signature_url)) {
                $this->deletePublicFile($school->signature_url);
            }
            $data['signature_url'] = null;
        }

        $sessionId = $data['current_session_id'] ?? null;
        $termId = $data['current_term_id'] ?? null;

        $previousSessionId = $school->current_session_id;
        $previousTermId = $school->current_term_id;

        if (array_key_exists('current_session_id', $data) && $sessionId !== null) {
            $session = Session::where('id', $sessionId)
                ->where('school_id', $school->id)
                ->first();

            if (! $session) {
                return response()->json(['message' => 'Selected session was not found for this school.'], 404);
            }
        }

        if (array_key_exists('current_term_id', $data) && $termId !== null) {
            $term = Term::where('id', $termId)
                ->where('school_id', $school->id)
                ->first();

            if (! $term) {
                return response()->json(['message' => 'Selected term was not found for this school.'], 404);
            }

            if ($sessionId !== null && $term->session_id !== $sessionId) {
                return response()->json(['message' => 'The selected term does not belong to the chosen session.'], 422);
            }

            if ($sessionId === null) {
                $sessionId = $term->session_id;
                $data['current_session_id'] = $sessionId;
            }
        }

        if ($sessionId === null && array_key_exists('current_term_id', $data) && $termId === null && ! array_key_exists('current_session_id', $data)) {
            // When only term is being cleared ensure session remains untouched.
            unset($data['current_session_id']);
        }

        $school->fill($data);

        $termChangedWithinSameSession = false;

        if (array_key_exists('current_term_id', $data)) {
            $newSessionId = $sessionId ?? $school->current_session_id;
            $newTermId = $termId ?? $school->current_term_id;

            if ($newTermId !== null && $newTermId !== $previousTermId && $newSessionId === $previousSessionId && $newSessionId !== null) {
                $termChangedWithinSameSession = true;
            }
        }

        if ($school->isDirty()) {
            $school->save();

            if ($termChangedWithinSameSession) {
                \App\Models\Student::query()
                    ->where('school_id', $school->id)
                    ->where('current_session_id', $school->current_session_id)
                    ->update([
                        'current_term_id' => $school->current_term_id,
                    ]);
            }
        }

        return response()->json([
            'message' => 'School profile updated successfully',
            'school' => $school->fresh([
                'currentSession:id,name,slug,start_date,end_date,status',
                'currentTerm:id,name,session_id,start_date,end_date,status',
            ]),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/v1/user",
     *     summary="Update School Admin profile",
     *     tags={"school-v1.0"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Jane Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="jane@example.com"),
     *             @OA\Property(property="old_password", type="string", example="currentPassword123"),
     *             @OA\Property(property="password", type="string", example="newPassword456"),
     *             @OA\Property(property="password_confirmation", type="string", example="newPassword456")
     *         )
     *     ),
     *     @OA\Response(response=200, description="School Admin profile updated successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */

    public function updateSchoolAdminProfile(Request $request)
        {
            $user = Auth::user();
        
            $rules = [
                'name' => 'string|max:255',
                'email' => 'string|email|max:255|unique:users,email,' . $user->id,
            ];
        
            // If user is trying to change password, add password + old_password rules
            if ($request->filled('password')) {
                $rules['password'] = 'required|string|min:8|confirmed';
                $rules['old_password'] = 'required|string';
            }
        
            $validator = Validator::make($request->all(), $rules);
        
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
        
            // Check old password
            if ($request->filled('password') && !Hash::check($request->old_password, $user->password)) {
                return response()->json(['old_password' => ['Old password is incorrect']], 422);
            }
        
            $data = $request->only(['name', 'email']);
        
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }
        
            $user->update($data);
        
            return response()->json([
                'message' => 'User profile updated successfully',
                'user' => $user
            ]);
        }

    /**
     * @OA\Get(
     *     path="/v1/user",
     *     summary="Get the authenticated School Admin's profile",
     *     tags={"school-v1.0"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="User profile returned"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function showSchoolAdminProfile(Request $request)
    {
        $user = $request->user();
        $schoolId = optional($user->school)->id ?? $user->school_id;

        $user->loadMissing([
            'school.currentSession:id,name,slug,start_date,end_date,status',
            'school.currentTerm:id,name,session_id,start_date,end_date,status',
            'parents' => function ($query) {
                $query
                    ->select([
                        'id',
                        'user_id',
                        'school_id',
                        'first_name',
                        'last_name',
                        'phone',
                    ])
                    ->withCount('students');
            },
            'staff:id,school_id,user_id,full_name,phone,role,gender,address,qualifications,employment_start_date,photo_url',
            'roles' => function ($relation) use ($schoolId) {
                $relation
                    ->where('roles.guard_name', config('permission.default_guard', 'sanctum'))
                    ->when($schoolId, function ($query) use ($schoolId) {
                        $query
                            ->whereNull('roles.school_id')
                            ->orWhere('roles.school_id', $schoolId);
                    })
                    ->with(['permissions' => function ($permissions) use ($schoolId) {
                        $permissions
                            ->where('permissions.guard_name', config('permission.default_guard', 'sanctum'))
                            ->when($schoolId, function ($query) use ($schoolId) {
                                $query
                                    ->whereNull('permissions.school_id')
                                    ->orWhere('permissions.school_id', $schoolId);
                            });
                    }]);
            },
        ]);

        $linkedStudentsCount = $user->parents
            ? $user->parents->sum('students_count')
            : 0;

        $studentCount = $schoolId
            ? Student::query()->where('school_id', $schoolId)->count()
            : 0;

        $parentCount = $schoolId
            ? SchoolParent::query()->where('school_id', $schoolId)->count()
            : 0;

        $teacherCount = 0;
        if ($schoolId) {
            $teacherQuery = Staff::query()->where('school_id', $schoolId);
            $teacherCount = (clone $teacherQuery)
                ->where(function ($query) {
                    $query->whereNull('role')
                        ->orWhereRaw('LOWER(role) LIKE ?', ['%teacher%']);
                })
                ->count();

            if ($teacherCount === 0) {
                $teacherCount = $teacherQuery->count();
            }
        }

        $permissionNames = $schoolId
            ? $this->withTeamContext($schoolId, function () use ($user) {
                return $user->getAllPermissions()
                    ->pluck('name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            })
            : [];

        $userData = $user->toArray();
        $userData['permissions'] = $permissionNames;

        return response()->json([
            'user' => $userData,
            'linked_students_count' => $linkedStudentsCount,
            'student_count' => $studentCount,
            'parent_count' => $parentCount,
            'teacher_count' => $teacherCount,
        ]);
    }


    /**
     * @OA\Get(
     *     path="/v1/school",
     *     summary="Get the authenticated school's profile",
     *     tags={"school-v1.0"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="School profile returned"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function showSchoolProfile(Request $request)
        {
            $user = $request->user();            // same as Auth::user()
            $school = $user->school;             // eager-load if you want

            return response()->json([
                'school' => $school->loadMissing([
                    'currentSession:id,name,slug,start_date,end_date,status',
                    'currentTerm:id,name,session_id,start_date,end_date,status',
                ]),
            ]);
        }

    private function formatStoredFileUrl(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    private function deletePublicFile(?string $url): void
    {
        if (! $url) {
            return;
        }

        $appUrl = rtrim(config('app.url'), '/');
        if (str_starts_with($url, $appUrl)) {
            $url = substr($url, strlen($appUrl));
        }

        $prefix = '/storage/';
        if (str_starts_with($url, $prefix)) {
            $path = substr($url, strlen($prefix));
            if ($path !== '') {
                Storage::disk('public')->delete($path);
            }
        } elseif (! str_contains($url, '://')) {
            Storage::disk('public')->delete(ltrim($url, '/'));
        }
    }

    private function generateUniqueSchoolSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        if ($baseSlug === '') {
            $baseSlug = 'school';
        }

        $existingSlugs = School::query()
            ->where('slug', 'like', $baseSlug.'%')
            ->lockForUpdate()
            ->pluck('slug')
            ->all();

        if (! in_array($baseSlug, $existingSlugs, true)) {
            return $baseSlug;
        }

        $suffix = 1;
        do {
            $candidate = $baseSlug.'-'.$suffix;
            $suffix++;
        } while (in_array($candidate, $existingSlugs, true));

        return $candidate;
    }

    private function generateSchoolAcronym(string $name): string
    {
        $words = collect(preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY));

        $acronym = $words
            ->map(fn (string $word) => mb_substr($word, 0, 1))
            ->implode('');

        $acronym = Str::upper(Str::of($acronym)->replaceMatches('/[^A-Z]/', ''));

        if ($acronym === '') {
            $acronym = Str::upper(mb_substr($name, 0, 3));
        }

        return Str::limit($acronym ?: 'SCH', 5, '');
    }

    /**
     * Execute a callback within the context of the authenticated school for permission checks.
     *
     * @template TReturn
     *
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    private function withTeamContext(?string $schoolId, callable $callback)
    {
        if (! $schoolId) {
            return $callback();
        }

        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = method_exists($registrar, 'getPermissionsTeamId')
            ? $registrar->getPermissionsTeamId()
            : null;

        $registrar->setPermissionsTeamId($schoolId);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }

    private function handleEmailVerification(User $user): void
    {
        if (! config('features.email_verification')) {
            if (! $user->email_verified_at) {
                $user->forceFill([
                    'email_verified_at' => now(),
                ])->save();
            }

            return;
        }

        $tokenValue = Str::random(64);
        $hashedToken = hash('sha256', $tokenValue);
        $ttlMinutes = max((int) config('features.email_verification_ttl_minutes', 1440), 5);
        $expiresAt = now()->addMinutes($ttlMinutes);

        EmailVerificationToken::where('user_id', $user->id)->delete();

        EmailVerificationToken::create([
            'user_id' => $user->id,
            'token' => $hashedToken,
            'expires_at' => $expiresAt,
        ]);

        $verificationUrl = url('/api/v1/email/verify?token=' . $tokenValue);

        try {
            Mail::to($user->email)->send(new VerifyEmail($user, $verificationUrl, $expiresAt));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
