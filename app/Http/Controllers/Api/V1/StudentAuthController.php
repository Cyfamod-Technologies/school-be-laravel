<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\Student;
use App\Models\Term;
use App\Models\ResultPin;
use App\Models\Result;
use App\Http\Controllers\ResultViewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="school-v2.6",
 *     description="v2.6 – Student Portal"
 * )
 */
class StudentAuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/student/login",
     *     tags={"school-v2.6"},
     *     summary="Student login",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"admission_no","password"},
     *             @OA\Property(property="admission_no", type="string", example="NC001-2024/2025/1"),
     *             @OA\Property(property="password", type="string", format="password", example="secret")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Logged in"),
     *     @OA\Response(response=422, description="Invalid credentials")
     * )
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'admission_no' => ['required', 'string'],
            'password' => ['nullable', 'string', 'required_without:first_name'],
            'first_name' => ['nullable', 'string', 'required_without:password'],
        ]);

        $student = Student::query()
            ->with([
                'school',
                'school.currentSession:id,name,slug',
                'school.currentTerm:id,name,session_id',
                'school_class:id,name,school_id',
                'class_arm:id,name',
                'session:id,name',
                'term:id,name',
                'parent:id,first_name,last_name,middle_name,phone,user_id',
                'parent.user:id,email',
                'blood_group:id,name',
            ])
            ->where('admission_no', $credentials['admission_no'])
            ->first();

        if (! $student) {
            throw ValidationException::withMessages([
                'admission_no' => ['Invalid admission number or credentials.'],
            ]);
        }

        if (! empty($credentials['password'])) {
            if (empty($student->portal_password) || ! Hash::check($credentials['password'], $student->portal_password)) {
                throw ValidationException::withMessages([
                    'admission_no' => ['Invalid admission number or credentials.'],
                ]);
            }
        } else {
            $inputName = Str::lower(trim((string) ($credentials['first_name'] ?? '')));
            $studentName = Str::lower(trim((string) $student->first_name));

            if ($inputName === '' || $inputName !== $studentName) {
                throw ValidationException::withMessages([
                    'admission_no' => ['Invalid admission number or credentials.'],
                ]);
            }
        }

        $student->loadMissing(['school_class.subjects:id,name']);

        $token = $student->createToken('student-portal', ['student'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'student' => $this->transformStudent($student),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/student/logout",
     *     tags={"school-v2.6"},
     *     summary="Student logout",
     *     @OA\Response(response=200, description="Logged out")
     * )
     */
    public function logout(Request $request)
    {
        $student = $this->resolveStudentUser($request);

        $student->tokens()
            ->where('id', optional($student->currentAccessToken())->id)
            ->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/student/profile",
     *     tags={"school-v2.6"},
     *     summary="Get student profile",
     *     @OA\Response(response=200, description="Profile returned"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function profile(Request $request)
    {
        $student = $this->resolveStudentUser($request)->load([
            'school',
            'school.currentSession:id,name,slug',
            'school.currentTerm:id,name,session_id',
            'school_class:id,name,school_id',
            'school_class.subjects:id,name',
            'class_arm:id,name',
            'session:id,name',
            'term:id,name',
            'parent:id,first_name,last_name,middle_name,phone,user_id',
            'parent.user:id,email',
            'blood_group:id,name',
        ]);

        return response()->json([
            'student' => $this->transformStudent($student),
        ]);
    }

    public function sessions(Request $request)
    {
        $student = $this->resolveStudentUser($request);

        $admissionDate = $student->admission_date;

        $sessions = Session::query()
            ->where('school_id', $student->school_id)
            ->orderByRaw('COALESCE(start_date, created_at) ASC')
            ->get(['id', 'name', 'start_date', 'created_at']);

        $sessionPayload = $sessions->map(function (Session $session) use ($student) {
            $terms = Term::query()
                ->where('session_id', $session->id)
                ->where('school_id', $student->school_id)
                ->orderBy('start_date')
                ->get(['id', 'name', 'session_id']);

            return [
                'id' => $session->id,
                'name' => $session->name,
                'start_date' => $session->start_date,
                'terms' => $terms->map(fn (Term $term) => [
                    'id' => $term->id,
                    'name' => $term->name,
                ]),
            ];
        });

        return response()->json(['data' => $sessionPayload]);
    }

    public function previewResult(Request $request)
    {
        $student = $this->resolveStudentUser($request);

        $validated = $request->validate([
            'session_id' => ['required', 'uuid'],
            'term_id' => ['required', 'uuid'],
            'pin_code' => ['required', 'string'],
        ]);

        $normalizedPin = preg_replace('/\s+/', '', $validated['pin_code']);

        $pin = ResultPin::query()
            ->where('student_id', $student->id)
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->where('status', 'active')
            ->first();

        if (! $pin) {
            throw ValidationException::withMessages([
                'pin_code' => ['Invalid or inactive PIN for the selected session/term.'],
            ]);
        }

        $storedPin = preg_replace('/\s+/', '', (string) $pin->pin_code);

        if (! hash_equals($storedPin, $normalizedPin)) {
            throw ValidationException::withMessages([
                'pin_code' => ['Invalid PIN.'],
            ]);
        }

        if ($pin->expires_at && $pin->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'pin_code' => ['This PIN has expired.'],
            ]);
        }

        if ($pin->max_usage && $pin->use_count >= $pin->max_usage) {
            throw ValidationException::withMessages([
                'pin_code' => ['PIN usage limit reached.'],
            ]);
        }

        $pin->increment('use_count');

        $results = Result::query()
            ->where('student_id', $student->id)
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->with([
                'subject:id,name,code',
                'assessment_component:id,name,label,order',
                'grade_range:id,grade_label',
            ])
            ->get();

        $subjectResults = $results
            ->groupBy('subject_id')
            ->map(function ($items) {
                $first = $items->first();
                $components = $items
                    ->filter(fn (Result $row) => $row->assessment_component !== null)
                    ->sortBy(fn (Result $row) => $row->assessment_component->order ?? PHP_INT_MAX)
                    ->map(function (Result $row) {
                        $component = $row->assessment_component;
                        $label = strtoupper($component->label ?? $component->name ?? 'Component');
                        return [
                            'id' => $component->id,
                            'label' => $label,
                            'score' => $row->total_score,
                        ];
                    })
                    ->filter(fn (array $entry) => $entry['score'] !== null)
                    ->values()
                    ->all();

                $summaryRow = $items->first(fn (Result $row) => $row->assessment_component_id === null);
                $total = $summaryRow?->total_score;

                if ($total === null && ! empty($components)) {
                    $scores = array_map(fn (array $entry) => $entry['score'], $components);
                    $numericScores = array_filter($scores, fn ($score) => is_numeric($score));
                    $total = ! empty($numericScores) ? array_sum($numericScores) : null;
                }

                return [
                    'subject' => $first?->subject?->name,
                    'components' => $components,
                    'total' => $total,
                ];
            })
            ->values();

        return response()->json([
            'student' => $this->transformStudent($student),
            'results' => $subjectResults,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/student/results/download",
     *     tags={"school-v2.6"},
     *     summary="Download student result",
     *     description="Downloads the student's result for a given session and term.",
     *     @OA\Parameter(name="session_id", in="query", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="term_id", in="query", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Result file or payload returned"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function downloadResult(Request $request)
    {
        $student = $this->resolveStudentFromToken($request);

        if (! $student) {
            abort(401, 'Unauthenticated.');
        }

        $validated = $request->validate([
            'session_id' => ['required', 'uuid'],
            'term_id' => ['required', 'uuid'],
        ]);

        $results = Result::query()
            ->where('student_id', $student->id)
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->with([
                'subject:id,name,code',
                'assessment_component:id,name,label,order',
                'grade_range:id,grade_label,description,min_score,max_score',
            ])
            ->get();

        if ($results->isEmpty()) {
            abort(404, 'No results found for the selected session/term.');
        }

        $pages = collect([
            app(ResultViewController::class)->buildResultPageData(
                $student,
                $validated['session_id'],
                $validated['term_id'],
                $student->school_id
            ),
        ]);

        $studentName = trim(collect([$student->first_name, $student->middle_name, $student->last_name])->filter()->implode(' '));
        $view = View::make('result-bulk', [
            'pages' => $pages,
            'filters' => [
                'session' => optional($student->session()->find($validated['session_id']))?->name,
                'term' => optional($student->term()->find($validated['term_id']))?->name,
                'class' => optional($student->school_class)->name,
                'class_arm' => optional($student->class_arm)->name,
                'class_section' => optional($student->class_section)->name,
                'student_count' => 1,
                'total_students' => 1,
            ],
            'generatedAt' => now()->format('jS F Y, h:i A'),
            'documentTitle' => $studentName ? "{$studentName} | Result Slip" : 'Result Slip',
        ]);

        return response($view->render())
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    private function resolveStudentUser(Request $request): Student
    {
        $user = $request->user('student');

        if ($user instanceof Student) {
            return $user;
        }

        $user = $request->user();

        if ($user instanceof Student) {
            return $user;
        }

        abort(401, 'Unauthenticated.');
    }

    /**
     * Resolve a Student directly from the Bearer token without relying on
     * middleware.  This is used by the public download route so that
     * cookie-encryption mismatches between the Next.js proxy and the
     * browser don't block the request.
     */
    private function resolveStudentFromToken(Request $request): ?Student
    {
        // 1. Try the guards first (works when middleware already ran)
        $user = $request->user('student') ?? $request->user();
        if ($user instanceof Student) {
            return $user;
        }

        // 2. Manual Sanctum token lookup
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return null;
        }

        // Sanctum plain-text tokens look like  "<id>|<token>"
        if (! Str::contains($bearer, '|')) {
            return null;
        }

        [$tokenId, $plainToken] = explode('|', $bearer, 2);

        $accessToken = \Laravel\Sanctum\PersonalAccessToken::find($tokenId);

        if (! $accessToken || ! hash_equals($accessToken->token, hash('sha256', $plainToken))) {
            return null;
        }

        $student = $accessToken->tokenable;

        return $student instanceof Student ? $student : null;
    }

    private function transformStudent(Student $student): array
    {
        $schoolCurrentSession = $student->school?->currentSession;
        $schoolCurrentTerm = $student->school?->currentTerm;

        return [
            'id' => $student->id,
            'admission_no' => $student->admission_no,
            'first_name' => $student->first_name,
            'middle_name' => $student->middle_name,
            'last_name' => $student->last_name,
            'gender' => $student->gender,
            'date_of_birth' => optional($student->date_of_birth)?->toDateString(),
            'state_of_origin' => $student->state_of_origin,
            'nationality' => $student->nationality,
            'address' => $student->address,
            'house' => $student->house,
            'club' => $student->club,
            'lga_of_origin' => $student->lga_of_origin,
            'photo_url' => $student->photo_url,
            'blood_group' => $student->blood_group?->only(['id', 'name']),
            'medical_information' => $student->medical_information,
            'parent' => $student->parent ? [
                'id' => $student->parent->id,
                'name' => trim(collect([$student->parent->first_name, $student->parent->middle_name, $student->parent->last_name])->filter()->implode(' ')),
                'phone' => $student->parent->phone,
                'email' => $student->parent->user?->email ?? $student->parent->email ?? null,
                'first_name' => $student->parent->first_name,
                'last_name' => $student->parent->last_name,
                'middle_name' => $student->parent->middle_name,
            ] : null,
            'school' => $student->school?->only(['id', 'name', 'logo_url', 'address', 'phone']),
            'current_session' => ($schoolCurrentSession ?? $student->session)?->only(['id', 'name']),
            'current_term' => ($schoolCurrentTerm ?? $student->term)?->only(['id', 'name']),
            'school_class' => $student->school_class?->only(['id', 'name']),
            'class_arm' => $student->class_arm?->only(['id', 'name']),
            'subjects' => $student->school_class?->subjects?->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
            ])->values()->all() ?? [],
        ];
    }

    /**
     * Get parent information for the current student
     */
    public function getParent(Request $request)
    {
        $student = $this->resolveStudentUser($request)->load([
            'parent:id,first_name,last_name,middle_name,phone,user_id',
            'parent.user:id,email',
        ]);

        return response()->json([
            'parent' => $student->parent ? [
                'id' => $student->parent->id,
                'first_name' => $student->parent->first_name,
                'last_name' => $student->parent->last_name,
                'middle_name' => $student->parent->middle_name,
                'phone' => $student->parent->phone,
                'email' => $student->parent->user?->email ?? null,
            ] : null,
        ]);
    }

    /**
     * Update or create parent information for the current student
     */
    public function upsertParent(Request $request, Student $student)
    {
        $currentStudent = $this->resolveStudentUser($request);

        // Verify the current student can only update their own parent info
        if ($currentStudent->id !== $student->id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'relationship' => 'nullable|string|in:mother,father,guardian,other',
            'occupation' => 'nullable|string|max:255',
            'address' => 'nullable|string',
        ]);

        // Find or create parent
        if ($student->parent_id) {
            // Update existing parent
            $parent = $student->parent;
            $parent->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? $parent->middle_name,
                'phone' => $validated['phone'],
                'occupation' => $validated['occupation'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);

            // Update email if parent has associated user
            if ($parent->user_id && $validated['email']) {
                $parent->user->update(['email' => $validated['email']]);
            }
        } else {
            // Create new parent and associated user
            $parentUser = \App\Models\User::create([
                'id' => (string) Str::uuid(),
                'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'email' => $validated['email'],
                'school_id' => $student->school_id,
                'password' => Hash::make(Str::random(16)),
            ]);

            $parent = \App\Models\SchoolParent::create([
                'id' => (string) Str::uuid(),
                'school_id' => $student->school_id,
                'user_id' => $parentUser->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'phone' => $validated['phone'],
                'occupation' => $validated['occupation'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);

            // Link parent to student
            $student->update(['parent_id' => $parent->id]);
        }

        return response()->json([
            'parent' => [
                'id' => $parent->id,
                'first_name' => $parent->first_name,
                'last_name' => $parent->last_name,
                'middle_name' => $parent->middle_name,
                'phone' => $parent->phone,
                'email' => $parent->user?->email ?? null,
            ],
            'message' => 'Parent information updated successfully',
        ]);
    }

    /**
     * Update the authenticated student's own bio-data (profile).
     */
    public function updateProfile(Request $request)
    {
        $student = $this->resolveStudentUser($request);

        $validated = $request->validate([
            'first_name'          => 'sometimes|string|max:255',
            'middle_name'         => 'nullable|string|max:255',
            'last_name'           => 'sometimes|string|max:255',
            'gender'              => ['sometimes', \Illuminate\Validation\Rule::in(['male','female','other','others','Male','Female','Other','Others','m','f','o','M','F','O'])],
            'date_of_birth'       => 'sometimes|date',
            'nationality'         => 'nullable|string|max:255',
            'state_of_origin'     => 'nullable|string|max:255',
            'lga_of_origin'       => 'nullable|string|max:255',
            'house'               => 'nullable|string|max:255',
            'club'                => 'nullable|string|max:255',
            'address'             => 'nullable|string|max:500',
            'medical_information' => 'nullable|string',
            'blood_group_id'      => 'sometimes|nullable|uuid|exists:blood_groups,id',
        ]);

        // Handle passport/photo file upload
        if ($request->hasFile('photo_url')) {
            $photoPath = $request->file('photo_url')->store('students/photos', 'public');
            if ($student->getRawOriginal('photo_url')) {
                $this->deleteStudentPublicFile($student->getRawOriginal('photo_url'));
            }
            $validated['photo_url'] = Storage::disk('public')->url($photoPath);
        }

        // Normalise empty strings to null
        foreach (['house', 'club', 'nationality', 'state_of_origin', 'lga_of_origin', 'address', 'medical_information'] as $field) {
            if (array_key_exists($field, $validated)) {
                $value = is_string($validated[$field]) ? trim($validated[$field]) : $validated[$field];
                $validated[$field] = ($value === '' || $value === null) ? null : $value;
            }
        }

        $student->update($validated);

        $student->load([
            'school',
            'school.currentSession:id,name,slug',
            'school.currentTerm:id,name,session_id',
            'school_class:id,name,school_id',
            'school_class.subjects:id,name',
            'class_arm:id,name',
            'session:id,name',
            'term:id,name',
            'parent:id,first_name,last_name,middle_name,phone,user_id',
            'parent.user:id,email',
            'blood_group:id,name',
        ]);

        return response()->json([
            'student' => $this->transformStudent($student),
            'message' => 'Profile updated successfully',
        ]);
    }

    /**
     * Delete a file from public storage (student context).
     */
    private function deleteStudentPublicFile(?string $url): void
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

    /**
     * Update parent information for the current student
     */
    public function updateParent(Request $request)
    {
        $student = $this->resolveStudentUser($request);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'relationship' => 'nullable|string|in:mother,father,guardian,other',
            'occupation' => 'nullable|string|max:255',
            'address' => 'nullable|string',
        ]);

        // Find or create parent
        if ($student->parent_id) {
            // Update existing parent
            $parent = $student->parent;
            $parent->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? $parent->middle_name,
                'phone' => $validated['phone'],
                'occupation' => $validated['occupation'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);

            // Update email if parent has associated user
            if ($parent->user_id && $validated['email']) {
                $parent->user->update(['email' => $validated['email']]);
            }
        } else {
            // Create new parent and associated user
            $parentUser = \App\Models\User::create([
                'id' => (string) Str::uuid(),
                'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'email' => $validated['email'],
                'school_id' => $student->school_id,
                'password' => Hash::make(Str::random(16)),
            ]);

            $parent = \App\Models\SchoolParent::create([
                'id' => (string) Str::uuid(),
                'school_id' => $student->school_id,
                'user_id' => $parentUser->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'phone' => $validated['phone'],
                'occupation' => $validated['occupation'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);

            // Link parent to student
            $student->update(['parent_id' => $parent->id]);
        }

        return response()->json([
            'parent' => [
                'id' => $parent->id,
                'first_name' => $parent->first_name,
                'last_name' => $parent->last_name,
                'middle_name' => $parent->middle_name,
                'phone' => $parent->phone,
                'email' => $parent->user?->email ?? null,
            ],
            'message' => 'Parent information updated successfully',
        ]);
    }
}
