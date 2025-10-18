<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="school-v1.4",
 *     description="student Management"
 * )
 */
class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Get(
     *      path="/v1/students",
     *      operationId="getStudentsList",
     *      tags={"school-v1.4"},
     *      summary="Get list of students",
     *      description="Returns list of students",
     *      @OA\Parameter(
     *          name="search",
     *          description="Search by name or admission number",
     *          in="query",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="school_class_id",
     *          description="Filter by class",
     *          in="query",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="parent_id",
     *          description="Filter by parent",
     *          in="query",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *      )
     * )
     */
    public function index(Request $request)
    {
        Student::fixLegacyForeignKeys();
        $perPage = max((int) $request->input('per_page', 10), 1);

        $students = $request->user()->school->students()
            ->with($this->studentRelations())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('admission_no', 'like', "%{$search}%")
                        ->orWhereHas('school_class', function ($classQuery) use ($search) {
                            $classQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('class_arm', function ($armQuery) use ($search) {
                            $armQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('parent', function ($parentQuery) use ($search) {
                            $parentQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('session_id') || $request->filled('current_session_id'), function ($query) use ($request) {
                $sessionId = $request->input('current_session_id', $request->input('session_id'));
                $query->where('current_session_id', $sessionId);
            })
            ->when($request->filled('term_id') || $request->filled('current_term_id'), function ($query) use ($request) {
                $termId = $request->input('current_term_id', $request->input('term_id'));
                $query->where('current_term_id', $termId);
            })
            ->when($request->filled('class_id') || $request->filled('school_class_id'), function ($query) use ($request) {
                $classId = $request->input('school_class_id', $request->input('class_id'));
                $query->where('school_class_id', $classId);
            })
            ->when($request->filled('class_arm_id'), function ($query) use ($request) {
                $query->where('class_arm_id', $request->class_arm_id);
            })
            ->when($request->filled('class_section_id'), function ($query) use ($request) {
                $query->where('class_section_id', $request->class_section_id);
            })
            ->when($request->filled('parent_id'), function ($query) use ($request) {
                $query->where('parent_id', $request->parent_id);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', strtolower($request->status));
            })
            ->when($request->filled('sortBy'), function ($query) use ($request) {
                $allowed = ['first_name', 'last_name', 'admission_no', 'created_at'];
                $column = $request->input('sortBy');

                if (in_array($column, $allowed, true)) {
                    $direction = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';
                    $query->orderBy($column, $direction);
                }
            })
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($students);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Post(
     *      path="/v1/students",
     *      operationId="storeStudent",
     *      tags={"school-v1.4"},
     *      summary="Store new student",
     *      description="Returns student data",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="admission_no", type="string", example="2023/123"),
     *              @OA\Property(property="first_name", type="string", example="John"),
     *              @OA\Property(property="middle_name", type="string", example=""),
     *              @OA\Property(property="last_name", type="string", example="Doe"),
     *              @OA\Property(property="gender", type="string", example="male"),
     *              @OA\Property(property="date_of_birth", type="string", format="date", example="2010-01-01"),
     *              @OA\Property(property="nationality", type="string", example="Nigerian"),
     *              @OA\Property(property="state_of_origin", type="string", example="Lagos"),
     *              @OA\Property(property="lga_of_origin", type="string", example="Ikeja"),
     *              @OA\Property(property="house", type="string", example="Green"),
     *              @OA\Property(property="club", type="string", example="Debate"),
     *              @OA\Property(property="current_session_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="current_term_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="school_class_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="class_arm_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="class_section_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="parent_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="admission_date", type="string", format="date", example="2023-09-01"),
     *              @OA\Property(property="photo_url", type="string", example=""),
     *              @OA\Property(property="status", type="string", example="active"),
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request"
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *      )
     * )
     */
    public function store(Request $request)
    {
        Student::fixLegacyForeignKeys();
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $this->prepareRelationshipInput($request);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => ['required', Rule::in(['male', 'female', 'other', 'others', 'Male', 'Female', 'Other', 'Others', 'm', 'f', 'o', 'M', 'F', 'O'])],
            'date_of_birth' => 'required|date',
            'nationality' => 'nullable|string|max:255',
            'state_of_origin' => 'nullable|string|max:255',
            'lga_of_origin' => 'nullable|string|max:255',
            'house' => 'nullable|string|max:255',
            'club' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'medical_information' => 'nullable|string',
            'blood_group_id' => 'nullable|uuid|exists:blood_groups,id',
            'current_session_id' => 'required|exists:sessions,id',
            'current_term_id' => 'required|exists:terms,id',
            'school_class_id' => 'required|exists:classes,id',
            'class_arm_id' => 'required|exists:class_arms,id',
            'class_section_id' => 'nullable|exists:class_sections,id',
            'parent_id' => 'required|exists:parents,id',
            'admission_date' => 'required|date',
            'photo_url' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(['active', 'inactive', 'graduated', 'withdrawn'])],
        ]);

        $session = \App\Models\Session::findOrFail($validated['current_session_id']);
        $studentCountForSession = $school->students()->where('current_session_id', $session->id)->count();

        $studentData = $validated;
        $studentData['id'] = (string) Str::uuid();
        $studentData['school_id'] = $school->id;
        $studentData['admission_no'] = $session->name . '/' . ($studentCountForSession + 1);
        $studentData['status'] = strtolower($studentData['status']);

        if (array_key_exists('class_section_id', $studentData) && ! $studentData['class_section_id']) {
            $studentData['class_section_id'] = null;
        }

        if (array_key_exists('photo_url', $studentData) && ! $studentData['photo_url']) {
            $studentData['photo_url'] = null;
        }

        $student = Student::create($studentData);

        return response()->json([
            'data' => $student->load($this->studentRelations()),
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Student  $student
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Get(
     *      path="/v1/students/{id}",
     *      operationId="getStudentById",
     *      tags={"school-v1.4"},
     *      summary="Get student information",
     *      description="Returns student data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Student id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Resource Not Found"
     *      )
     * )
     */
    public function show(Request $request, Student $student)
    {
        Student::fixLegacyForeignKeys();
        if ($student->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json([
            'data' => $student->load($this->studentRelations()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Student  $student
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Put(
     *      path="/v1/students/{id}",
     *      operationId="updateStudent",
     *      tags={"school-v1.4"},
     *      summary="Update existing student",
     *      description="Returns updated student data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Student id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="admission_no", type="string", example="2023/123"),
     *              @OA\Property(property="first_name", type="string", example="John"),
     *              @OA\Property(property="middle_name", type="string", example=""),
     *              @OA\Property(property="last_name", type="string", example="Doe"),
     *              @OA\Property(property="gender", type="string", example="male"),
     *              @OA\Property(property="date_of_birth", type="string", format="date", example="2010-01-01"),
     *              @OA\Property(property="nationality", type="string", example="Nigerian"),
     *              @OA\Property(property="state_of_origin", type="string", example="Lagos"),
     *              @OA\Property(property="lga_of_origin", type="string", example="Ikeja"),
     *              @OA\Property(property="house", type="string", example="Green"),
     *              @OA\Property(property="club", type="string", example="Debate"),
     *              @OA\Property(property="current_session_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="current_term_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="school_class_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="class_arm_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="class_section_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="parent_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="admission_date", type="string", format="date", example="2023-09-01"),
     *              @OA\Property(property="photo_url", type="string", example=""),
     *              @OA\Property(property="status", type="string", example="active"),
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request"
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Resource Not Found"
     *      )
     * )
     */
    public function update(Request $request, Student $student)
    {
        Student::fixLegacyForeignKeys();
        if ($student->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $this->prepareRelationshipInput($request);

        $validated = $request->validate([
            'admission_no' => ['sometimes', 'string', 'max:255', Rule::unique('students', 'admission_no')->ignore($student->id)],
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => ['required', Rule::in(['male', 'female', 'other', 'others', 'Male', 'Female', 'Other', 'Others', 'm', 'f', 'o', 'M', 'F', 'O'])],
            'date_of_birth' => 'required|date',
            'nationality' => 'nullable|string|max:255',
            'state_of_origin' => 'nullable|string|max:255',
            'lga_of_origin' => 'nullable|string|max:255',
            'house' => 'nullable|string|max:255',
            'club' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'medical_information' => 'nullable|string',
            'blood_group_id' => 'sometimes|nullable|uuid|exists:blood_groups,id',
            'current_session_id' => 'required|exists:sessions,id',
            'current_term_id' => 'required|exists:terms,id',
            'school_class_id' => 'required|exists:classes,id',
            'class_arm_id' => 'required|exists:class_arms,id',
            'class_section_id' => 'nullable|exists:class_sections,id',
            'parent_id' => 'required|exists:parents,id',
            'admission_date' => 'required|date',
            'photo_url' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(['active', 'inactive', 'graduated', 'withdrawn'])],
        ]);

        if (array_key_exists('class_section_id', $validated) && ! $validated['class_section_id']) {
            $validated['class_section_id'] = null;
        }

        if (array_key_exists('photo_url', $validated) && ! $validated['photo_url']) {
            $validated['photo_url'] = null;
        }

        $validated['status'] = strtolower($validated['status']);

        if (! array_key_exists('admission_no', $validated)) {
            $validated['admission_no'] = $student->admission_no;
        }

        $student->update($validated);

        return response()->json([
            'data' => $student->fresh()->load($this->studentRelations()),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Student  $student
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Delete(
     *      path="/v1/students/{id}",
     *      operationId="deleteStudent",
     *      tags={"school-v1.4"},
     *      summary="Delete existing student",
     *      description="Deletes a record and returns no content",
     *      @OA\Parameter(
     *          name="id",
     *          description="Student id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=204,
     *          description="Successful operation",
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Resource Not Found"
     *      )
     * )
     */
    public function destroy(Request $request, Student $student)
    {
        if ($student->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        if ($student->results()->exists() || $student->attendances()->exists() || $student->fee_payments()->exists()) {
            return response()->json(['message' => 'Cannot delete student with dependent records.'], 409);
        }

        $student->delete();

        return response()->json(null, 204);
    }

    protected function studentRelations(): array
    {
        return ['school_class', 'class_arm', 'class_section', 'parent', 'session', 'term', 'blood_group'];
    }

    protected function prepareRelationshipInput(Request $request): void
    {
        $classIdentifier = $request->input('school_class_id', $request->input('class_id'));

        if (in_array($classIdentifier, [null, '', '0', 0], true)) {
            $request->request->remove('school_class_id');
        } else {
            $request->merge(['school_class_id' => (string) $classIdentifier]);
        }

        foreach (['school_class_id', 'class_arm_id', 'class_section_id', 'parent_id', 'current_session_id', 'current_term_id', 'blood_group_id'] as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $value = $request->input($field);

            if (in_array($value, [null, '', '0', 0], true)) {
                $request->merge([$field => null]);
            } else {
                $request->merge([$field => (string) $value]);
            }
        }
    }
}
