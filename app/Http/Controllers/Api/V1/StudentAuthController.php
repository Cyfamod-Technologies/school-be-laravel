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
use Illuminate\Support\Str;

class StudentAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'admission_no' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $student = Student::query()
            ->with([
                'school',
                'school_class:id,name,school_id',
                'class_arm:id,name',
                'session:id,name',
                'term:id,name',
                'parent:id,first_name,last_name,middle_name,phone',
                'blood_group:id,name',
            ])
            ->where('admission_no', $credentials['admission_no'])
            ->first();

        if (! $student || empty($student->portal_password) || ! Hash::check($credentials['password'], $student->portal_password)) {
            throw ValidationException::withMessages([
                'admission_no' => ['Invalid admission number or password.'],
            ]);
        }

        $student->loadMissing(['school_class.subjects:id,name']);

        $token = $student->createToken('student-portal', ['student'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'student' => $this->transformStudent($student),
        ]);
    }

    public function logout(Request $request)
    {
        $student = $this->resolveStudentUser($request);

        $student->tokens()
            ->where('id', optional($student->currentAccessToken())->id)
            ->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function profile(Request $request)
    {
        $student = $this->resolveStudentUser($request)->load([
            'school',
            'school_class:id,name,school_id',
            'school_class.subjects:id,name',
            'class_arm:id,name',
            'session:id,name',
            'term:id,name',
            'parent:id,first_name,last_name,middle_name,phone',
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
            ->with(['subject:id,name,code', 'grade_range:id,grade_label'])
            ->get()
            ->map(fn (Result $row) => [
                'subject' => $row->subject?->name,
                'code' => $row->subject?->code,
                'score' => $row->total_score,
                'grade' => $row->grade_range?->grade_label,
                'remarks' => $row->remarks,
            ]);

        return response()->json([
            'student' => $this->transformStudent($student),
            'results' => $results,
        ]);
    }

    public function downloadResult(Request $request)
    {
        $student = $this->resolveStudentUser($request);

        $validated = $request->validate([
            'session_id' => ['required', 'uuid'],
            'term_id' => ['required', 'uuid'],
        ]);

        $pinExists = ResultPin::query()
            ->where('student_id', $student->id)
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->where('status', 'active')
            ->exists();

        if (! $pinExists) {
            abort(403, 'No PIN found for this session/term. Generate one from the school portal.');
        }

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
        ]);

        return response($view->render())
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    private function resolveStudentUser(Request $request): Student
    {
        $user = $request->user('student');

        if (! $user instanceof Student) {
            abort(403, 'Only students may access this endpoint.');
        }

        return $user;
    }

    private function transformStudent(Student $student): array
    {
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
            'blood_group' => $student->blood_group?->only(['id', 'name']),
            'medical_information' => $student->medical_information,
            'parent' => $student->parent ? [
                'id' => $student->parent->id,
                'name' => trim(collect([$student->parent->first_name, $student->parent->middle_name, $student->parent->last_name])->filter()->implode(' ')),
                'phone' => $student->parent->phone,
            ] : null,
            'school' => $student->school?->only(['id', 'name', 'logo_url', 'address', 'phone']),
            'current_session' => $student->session?->only(['id', 'name']),
            'current_term' => $student->term?->only(['id', 'name']),
            'school_class' => $student->school_class?->only(['id', 'name']),
            'class_arm' => $student->class_arm?->only(['id', 'name']),
            'subjects' => $student->school_class?->subjects?->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
            ])->values()->all() ?? [],
        ];
    }
}
