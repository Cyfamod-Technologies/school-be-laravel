<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
            ->when($admissionDate, function ($query) use ($admissionDate) {
                $query->whereDate('start_date', '>=', $admissionDate->startOfYear());
            })
            ->orderBy('start_date')
            ->get(['id', 'name', 'start_date'])
            ->map(fn (Session $session) => [
                'id' => $session->id,
                'name' => $session->name,
                'start_date' => $session->start_date,
            ]);

        return response()->json(['data' => $sessions]);
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
            'school' => $student->school?->only(['id', 'name', 'logo_url']),
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
