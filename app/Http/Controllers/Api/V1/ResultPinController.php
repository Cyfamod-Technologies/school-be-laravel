<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ResultPin;
use App\Models\Student;
use App\Services\ResultPinService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ResultPinController extends Controller
{
    public function __construct(private readonly ResultPinService $service)
    {
    }

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
