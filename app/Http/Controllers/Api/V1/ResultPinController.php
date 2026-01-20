<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ResultPin;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ResultPinService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="school-v1.9",
 *     description="v1.9 – Results, Components, Grading & Skills"
 * )
 */
class ResultPinController extends Controller
{
    public function __construct(private readonly ResultPinService $service)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/students/{student}/result-pins",
     *     tags={"school-v1.4","school-v1.9"},
     *     summary="List result PINs for a student",
     *     description="Returns a student's result PINs filtered by session and term.",
     *     @OA\Parameter(
     *         name="student",
     *         in="path",
     *         required=true,
     *         description="Student ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="session_id",
     *         in="query",
     *         required=false,
     *         description="Session ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="term_id",
     *         in="query",
     *         required=false,
     *         description="Term ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="List returned"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request, Student $student)
    {
        $this->ensurePermission($request, 'result.pin.view');
        $this->authorizeStudent($request, $student);

        $pins = $student->result_pins()
            ->with(['session:id,name', 'term:id,name'])
            ->when($request->filled('session_id'), fn ($query) => $query->where('session_id', $request->string('session_id')))
            ->when($request->filled('term_id'), fn ($query) => $query->where('term_id', $request->string('term_id')))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ResultPin $pin) => $this->transformPin($pin, true));

        return response()->json(['data' => $pins]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/students/{student}/result-pins",
     *     tags={"school-v1.4","school-v1.9"},
     *     summary="Generate a result PIN for a student",
     *     @OA\Parameter(
     *         name="student",
     *         in="path",
     *         required=true,
     *         description="Student ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"session_id","term_id"},
     *             @OA\Property(property="session_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="term_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="expires_at", type="string", format="date-time", example="2025-12-31T23:59:59Z"),
     *             @OA\Property(property="regenerate", type="boolean", example=false),
     *             @OA\Property(property="max_usage", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(response=201, description="PIN generated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, Student $student)
    {
        $this->ensurePermission($request, ['result.pin.generate', 'result.pin.manage']);
        $this->authorizeStudent($request, $student);

        $validated = $request->validate([
            'session_id' => ['required', 'uuid'],
            'term_id' => ['required', 'uuid'],
            'expires_at' => ['nullable', 'date'],
            'regenerate' => ['sometimes', 'boolean'],
            'max_usage' => ['nullable', 'integer', 'min:1'],
        ]);

        $options = [
            'expires_at' => $validated['expires_at'] ?? null,
            'regenerate' => (bool) ($validated['regenerate'] ?? false),
        ];

        if ($request->exists('max_usage')) {
            $options['max_usage'] = $validated['max_usage'];
        }

        $pin = $this->service->generateForStudent(
            $student,
            $validated['session_id'],
            $validated['term_id'],
            $request->user()->id,
            $options
        );

        return response()->json([
            'message' => 'Result PIN generated successfully.',
            'data' => $this->transformPin($pin),
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/result-pins/bulk",
     *     tags={"school-v1.4","school-v1.9"},
     *     summary="Bulk-generate result PINs",
     *     description="Generate result PINs for many students using class or explicit student filters.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"session_id","term_id"},
     *             @OA\Property(property="session_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="term_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="school_class_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="class_arm_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="student_ids", type="array", @OA\Items(type="string", format="uuid")),
     *             @OA\Property(property="regenerate", type="boolean", example=false),
     *             @OA\Property(property="expires_at", type="string", format="date-time", example="2025-12-31T23:59:59Z"),
     *             @OA\Property(property="max_usage", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Bulk generation completed"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function bulkGenerate(Request $request)
    {
        $this->ensurePermission($request, ['result.pin.generate', 'result.pin.manage']);
        $validated = $request->validate([
            'session_id' => ['required', 'uuid'],
            'term_id' => ['required', 'uuid'],
            'school_class_id' => ['nullable', 'uuid'],
            'class_arm_id' => ['nullable', 'uuid'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['uuid'],
            'regenerate' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'max_usage' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $school = $user->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $students = $this->resolveStudentsForBulk(
            $school->id,
            $validated,
            $request
        );

        if ($students->isEmpty()) {
            return response()->json([
                'message' => 'No students found for the provided filters.',
            ], 404);
        }

        $pins = [];
        $options = [
            'expires_at' => $validated['expires_at'] ?? null,
            'regenerate' => (bool) ($validated['regenerate'] ?? false),
        ];

        if ($request->exists('max_usage')) {
            $options['max_usage'] = $validated['max_usage'];
        }

        foreach ($students as $student) {
            $pins[] = $this->service->generateForStudent(
                $student,
                $validated['session_id'],
                $validated['term_id'],
                $user->id,
                $options
            );
        }

        return response()->json([
            'message' => 'Result PINs generated successfully.',
            'count' => count($pins),
            'data' => collect($pins)->map(fn (ResultPin $pin) => $this->transformPin($pin)),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/result-pins",
     *     tags={"school-v1.4","school-v1.9"},
     *     summary="List result PINs across the school",
     *     description="Requires session_id and term_id; supports filtering by student, class, arm, and status.",
     *     @OA\Parameter(name="session_id", in="query", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="term_id", in="query", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="student_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="school_class_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="class_arm_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="List returned"),
     *     @OA\Response(response=422, description="Missing required filters")
     * )
     */
    public function indexAll(Request $request)
    {
        $this->ensurePermission($request, ['result.pin.view', 'result.pin.manage']);
        $user = $request->user();
        $school = $user->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        if (! $request->filled('session_id') || ! $request->filled('term_id')) {
            return response()->json([
                'message' => 'Session and term are required to fetch result PINs.',
            ], 422);
        }

        $query = ResultPin::query()
            ->with(['student:id,first_name,last_name,admission_no,school_id', 'session:id,name', 'term:id,name'])
            ->whereHas('student', fn ($q) => $q->where('school_id', $school->id));

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->string('student_id'));
        }

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->string('session_id'));
        }

        if ($request->filled('term_id')) {
            $query->where('term_id', $request->string('term_id'));
        }

        if ($request->filled('school_class_id')) {
            $query->whereHas('student', fn ($q) => $q->where('school_class_id', $request->string('school_class_id')));
        }

        if ($request->filled('class_arm_id')) {
            $query->whereHas('student', fn ($q) => $q->where('class_arm_id', $request->string('class_arm_id')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $pins = $query->orderByDesc('created_at')->limit(200)->get()->map(fn (ResultPin $pin) => $this->transformPin($pin, true));

        return response()->json(['data' => $pins]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/result-pins/{resultPin}/invalidate",
     *     tags={"school-v1.4","school-v1.9"},
     *     summary="Invalidate a result PIN",
     *     @OA\Parameter(
     *         name="resultPin",
     *         in="path",
     *         required=true,
     *         description="Result PIN ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="PIN invalidated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function invalidate(Request $request, ResultPin $resultPin)
    {
        $this->ensurePermission($request, ['result.pin.invalidate', 'result.pin.manage']);
        $this->authorizePin($request, $resultPin);

        $pin = $this->service->invalidate($resultPin);

        return response()->json([
            'message' => 'Result PIN invalidated successfully.',
            'data' => $this->transformPin($pin),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/result-pins/cards/print",
     *     tags={"school-v1.4","school-v1.9"},
     *     summary="Print scratch cards for result PINs",
     *     description="Renders printable cards for a student or class for a given session/term.",
     *     @OA\Parameter(name="session_id", in="query", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="term_id", in="query", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="student_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="school_class_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="class_arm_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Printable HTML view"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function printCards(Request $request)
    {
        $validated = $request->validate([
            'session_id' => ['required', 'uuid'],
            'term_id' => ['required', 'uuid'],
            'school_class_id' => ['nullable', 'uuid'],
            'class_arm_id' => ['nullable', 'uuid'],
            'student_id' => ['nullable', 'uuid'],
            'autoprint' => ['sometimes'],
        ]);

        $user = $request->user();
        $school = $user?->school;
        $schoolId = $school?->id;

        if (! $schoolId && ! empty($validated['student_id'])) {
            $schoolId = Student::query()
                ->whereKey($validated['student_id'])
                ->value('school_id');
        }

        if (! $schoolId && ! empty($validated['school_class_id'])) {
            $schoolId = SchoolClass::query()
                ->whereKey($validated['school_class_id'])
                ->value('school_id');
        }

        if (! $schoolId && $user) {
            $schoolId = $user->roles()->value('school_id');
        }

        if (! $school && $schoolId) {
            $school = School::query()->find($schoolId);
        }

        if (! $schoolId || ! $school) {
            abort(403, 'You are not linked to any school.');
        }

        if (empty($validated['student_id']) && empty($validated['school_class_id'])) {
            return response()->view('result-pin-cards-error', [
                'message' => 'Select a student or class before printing scratch cards.',
            ], 422);
        }

        $pinsQuery = ResultPin::query()
            ->with([
                'student.school',
                'student.school_class',
                'student.class_arm',
                'session',
                'term',
            ])
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->whereHas('student', fn ($query) => $query->where('school_id', $schoolId));

        if (! empty($validated['student_id'])) {
            $pinsQuery->where('student_id', $validated['student_id']);
        } else {
            $pinsQuery->whereHas('student', function ($query) use ($validated) {
                if (! empty($validated['school_class_id'])) {
                    $query->where('school_class_id', $validated['school_class_id']);
                }
                if (! empty($validated['class_arm_id'])) {
                    $query->where('class_arm_id', $validated['class_arm_id']);
                }
            });
        }

        $pins = $pinsQuery
            ->join('students', 'students.id', '=', 'result_pins.student_id')
            ->select('result_pins.*')
            ->orderByRaw('LOWER(students.last_name) asc')
            ->orderByRaw('LOWER(students.first_name) asc')
            ->get();

        if ($pins->isEmpty()) {
            return response()->view('result-pin-cards-error', [
                'message' => 'No result PINs were found for the selected filters. Generate PINs first, then try printing again.',
            ], 422);
        }

        $session = $pins->first()->session;
        $term = $pins->first()->term;

        $cards = $pins->map(function (ResultPin $pin) {
            $student = $pin->student;
            $className = optional($student->school_class)->name;
            $armName = optional($student->class_arm)->name;
            $classLabel = trim(collect([$className, $armName])->filter()->implode(' - '));
            $studentName = trim(collect([
                $student?->first_name,
                $student?->middle_name,
                $student?->last_name,
            ])->filter()->implode(' '));

            return [
                'student_name' => $studentName ?: 'Student',
                'admission_no' => $student?->admission_no,
                'class_label' => $classLabel ?: 'Class not set',
                'pin_code' => $pin->pin_code,
                'expires_at' => $pin->expires_at ? $pin->expires_at->format('jS M, Y') : 'No expiry',
            ];
        });

        return view('result-pin-cards', [
            'school' => $school,
            'sessionName' => $session?->name,
            'termName' => $term?->name,
            'cards' => $cards,
            'cardPages' => $cards->chunk(6)->values(),
            'generatedAt' => Carbon::now()->format('jS F Y, h:i A'),
            'autoPrint' => $request->boolean('autoprint'),
            'schoolLogoUrl' => $this->resolveMediaUrl($school->logo_url),
            'studentPortalLink' => $school->student_portal_link,
        ]);
    }

    private function resolveMediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        $trimmed = ltrim($path, '/');

        if (Str::startsWith($trimmed, 'storage/')) {
            return asset($trimmed);
        }

        return asset('storage/' . $trimmed);
    }

    private function authorizeStudent(Request $request, Student $student): void
    {
        $user = $request->user();

        if (! $user || $user->school_id !== $student->school_id) {
            abort(403, 'You are not allowed to manage result PINs for this student.');
        }
    }

    private function authorizePin(Request $request, ResultPin $pin): void
    {
        $student = $pin->student;

        if (! $student) {
            abort(404, 'Result PIN is not associated with a student.');
        }

        $this->authorizeStudent($request, $student);
    }

    private function resolveStudentsForBulk(string $schoolId, array $validated, Request $request): Collection
    {
        if (! empty($validated['student_ids'])) {
            return Student::query()
                ->whereIn('id', $validated['student_ids'])
                ->where('school_id', $schoolId)
                ->get();
        }

        $query = Student::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('school_class_id');

        if (! empty($validated['school_class_id'])) {
            $query->where('school_class_id', $validated['school_class_id']);
        }

        if (! empty($validated['class_arm_id'])) {
            $query->where('class_arm_id', $validated['class_arm_id']);
        }

        return $query->get();
    }

    private function transformPin(ResultPin $pin, bool $withRelations = false): array
    {
        $student = $pin->relationLoaded('student') ? $pin->student : null;
        $session = $pin->relationLoaded('session') ? $pin->session : null;
        $term = $pin->relationLoaded('term') ? $pin->term : null;

        $studentName = null;
        if ($withRelations && $student) {
            $studentName = trim($student->first_name . ' ' . $student->last_name);
        }

        $sessionName = $withRelations && $session ? $session->name : null;
        $termName = $withRelations && $term ? $term->name : null;

        return [
            'id' => $pin->id,
            'student_id' => $pin->student_id,
            'session_id' => $pin->session_id,
            'term_id' => $pin->term_id,
            'pin_code' => $pin->pin_code,
            'status' => $pin->status,
            'expires_at' => optional($pin->expires_at)->toISOString(),
            'revoked_at' => optional($pin->revoked_at)->toISOString(),
            'created_at' => optional($pin->created_at)->toISOString(),
            'updated_at' => optional($pin->updated_at)->toISOString(),
            'use_count' => $pin->use_count,
            'max_usage' => $pin->max_usage,
            'student_name' => $studentName,
            'session_name' => $sessionName,
            'term_name' => $termName,
            'student' => $withRelations && $student ? [
                'id' => $student->id,
                'name' => $studentName,
                'admission_no' => $student->admission_no,
            ] : null,
            'session' => $withRelations && $session ? [
                'id' => $session->id,
                'name' => $sessionName,
            ] : null,
            'term' => $withRelations && $term ? [
                'id' => $term->id,
                'name' => $termName,
            ] : null,
        ];
    }
}
