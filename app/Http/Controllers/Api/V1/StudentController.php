<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\ClassSection;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\Teachers\TeacherAccessService;

/**
 * @OA\Tag(
 *     name="school-v1.4",
 *     description="v1.4 – Student Management, Skills & Results"
 * )
 * @OA\Tag(
 *     name="school-v1.9",
 *     description="v1.9 – Results, Components, Grading & Skills (supporting lookups)"
 * )
 * @OA\Tag(
 *     name="school-v2.0",
 *     description="v2.0 – Rollover, Promotions, Attendance, Fees, Roles (supporting lookups)"
 * )
 */
class StudentController extends Controller
{
    public function __construct(private TeacherAccessService $teacherAccess)
    {
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Get(
     *      path="/api/v1/students",
     *      operationId="getStudentsList",
     *      tags={"school-v1.4","school-v1.9","school-v2.0"},
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
        $scope = $this->teacherAccess->forUser($request->user());
        if (! $scope->isTeacher()) {
            $this->ensurePermission($request, 'students.view');
        }
        Student::fixLegacyForeignKeys();
        $perPage = max((int) $request->input('per_page', 10), 1);

        $query = Student::query()
            ->where('school_id', $request->user()->school_id)
            ->with($this->studentRelations())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('admission_no', 'like', "%{$search}%")
                        // Support full name search (first_name + last_name)
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$search}%"])
                        ->orWhereHas('school_class', function ($classQuery) use ($search) {
                            $classQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('class_arm', function ($armQuery) use ($search) {
                            $armQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('parent', function ($parentQuery) use ($search) {
                            $parentQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                // Support parent full name search
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                                ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$search}%"]);
                        });
                });
            })
            ->when($request->filled('current_session_id'), function ($query) use ($request) {
                $query->where('current_session_id', $request->input('current_session_id'));
            })
            ->when($request->filled('session_id') && ! $request->filled('current_session_id'), function ($query) use ($request) {
                $sessionId = $request->input('session_id');
                $termId = $request->input('term_id');
                $classId = $request->input('school_class_id', $request->input('class_id'));
                $classArmId = $request->input('class_arm_id');
                $classSectionId = $request->input('class_section_id');

                $query->where(function ($placementQuery) use ($sessionId, $termId, $classId, $classArmId, $classSectionId) {
                    $placementQuery->where(function ($currentPlacement) use ($sessionId, $classId, $classArmId, $classSectionId) {
                        $currentPlacement->where('current_session_id', $sessionId)
                            ->when($classId, fn ($builder, $value) => $builder->where('school_class_id', $value))
                            ->when($classArmId, fn ($builder, $value) => $builder->where('class_arm_id', $value))
                            ->when($classSectionId, fn ($builder, $value) => $builder->where('class_section_id', $value));
                    })
                        ->orWhereHas('results', function ($resultQuery) use ($sessionId, $termId, $classId, $classArmId, $classSectionId) {
                            $resultQuery->where('session_id', $sessionId)
                                ->when($termId, fn ($builder, $value) => $builder->where('term_id', $value))
                                ->when($classId, fn ($builder, $value) => $builder->where('school_class_id', $value))
                                ->when($classArmId, fn ($builder, $value) => $builder->where('class_arm_id', $value))
                                ->when($classSectionId, fn ($builder, $value) => $builder->where('class_section_id', $value));
                        });
                });
            })
            ->when($request->filled('current_term_id'), function ($query) use ($request) {
                $query->where('current_term_id', $request->input('current_term_id'));
            })
            ->when(
                ($request->filled('class_id') || $request->filled('school_class_id'))
                    && ! ($request->filled('session_id') && ! $request->filled('current_session_id')),
                function ($query) use ($request) {
                    $classId = $request->input('school_class_id', $request->input('class_id'));
                    $query->where('school_class_id', $classId);
                }
            )
            ->when(
                $request->filled('class_arm_id')
                    && ! ($request->filled('session_id') && ! $request->filled('current_session_id')),
                function ($query) use ($request) {
                    $query->where('class_arm_id', $request->class_arm_id);
                }
            )
            ->when(
                $request->filled('class_section_id')
                    && ! ($request->filled('session_id') && ! $request->filled('current_session_id')),
                function ($query) use ($request) {
                    $query->where('class_section_id', $request->input('class_section_id'));
                }
            )
            ->when($request->filled('parent_id'), function ($query) use ($request) {
                $query->where('parent_id', $request->parent_id);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $statuses = array_filter(array_map('strtolower', explode(',', $request->input('status'))));
                if (count($statuses) === 1) {
                    $query->where('status', reset($statuses));
                } elseif (count($statuses) > 1) {
                    $query->whereIn('status', array_values($statuses));
                }
            })
            ->when($request->filled('sortBy'), function ($query) use ($request) {
                $allowed = ['first_name', 'last_name', 'admission_no', 'created_at'];
                $column = $request->input('sortBy');

                if (in_array($column, $allowed, true)) {
                    $direction = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';
                    $query->orderBy($column, $direction);
                }
            });

        $scope->restrictStudentQuery($query);

        $students = $query->paginate($perPage)->withQueryString();

        if ($request->filled('session_id') && ! $request->filled('current_session_id')) {
            $studentIds = $students->getCollection()->pluck('id');
            $placements = Result::query()
                ->whereIn('student_id', $studentIds)
                ->where('session_id', $request->input('session_id'))
                ->when($request->input('term_id'), fn ($builder, $termId) => $builder->where('term_id', $termId))
                ->whereNotNull('school_class_id')->orderByDesc('created_at')
                ->get(['student_id', 'school_class_id', 'class_arm_id', 'class_section_id'])
                ->unique('student_id')
                ->keyBy('student_id');

            $classes = SchoolClass::query()
                ->whereIn('id', $placements->pluck('school_class_id')->filter()->unique())
                ->get()
                ->keyBy('id');
            $arms = ClassArm::query()
                ->whereIn('id', $placements->pluck('class_arm_id')->filter()->unique())
                ->get()
                ->keyBy('id');
            $sections = ClassSection::query()
                ->whereIn('id', $placements->pluck('class_section_id')->filter()->unique())
                ->get()
                ->keyBy('id');

            $students->getCollection()->each(function (Student $student) use ($placements, $classes, $arms, $sections) {
                $placement = $placements->get($student->id);
                if (! $placement) {
                    return;
                }

                $student->school_class_id = $placement->school_class_id;
                $student->class_arm_id = $placement->class_arm_id;
                $student->class_section_id = $placement->class_section_id;
                $student->setRelation('school_class', $classes->get($placement->school_class_id));
                $student->setRelation('class_arm', $arms->get($placement->class_arm_id));
                $student->setRelation('class_section', $sections->get($placement->class_section_id));
            });
        }

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
     *      path="/api/v1/students",
     *      operationId="storeStudent",
     *      tags={"school-v1.4","school-v1.9","school-v2.0"},
     *      summary="Store new student",
     *      description="Returns student data",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="admission_no", type="string", example="NC001-2024/2025/1"),
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
        $this->ensurePermission($request, 'students.create');
        Student::fixLegacyForeignKeys();
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $this->prepareRelationshipInput($request);

        $validated = $request->validate([
            'admission_no' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('students', 'admission_no'),
            ],
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
            'class_arm_id' => 'nullable|exists:class_arms,id',
            'parent_id' => 'nullable|exists:parents,id',
            'admission_date' => 'required|date',
            'photo_url' => 'nullable|string|max:255',
            'photo' => 'nullable|image|max:4096',
            'photo' => 'nullable|image|max:4096',
            'status' => ['required', Rule::in(['active', 'inactive', 'graduated', 'withdrawn'])],
        ]);

        $session = \App\Models\Session::findOrFail($validated['current_session_id']);

        $studentData = $validated;
        $studentData['id'] = (string) Str::uuid();
        $studentData['school_id'] = $school->id;
        $studentData['portal_password'] = '123456';
        $studentData['status'] = strtolower($studentData['status']);
        if (! array_key_exists('parent_id', $studentData) || ! $studentData['parent_id']) {
            $studentData['parent_id'] = null;
        }
        if (! array_key_exists('class_arm_id', $studentData) || ! $studentData['class_arm_id']) {
            $studentData['class_arm_id'] = null;
        }

        $studentData['class_section_id'] = null;

        foreach (['house', 'club'] as $field) {
            if (array_key_exists($field, $studentData)) {
                $value = $studentData[$field];
                if (is_string($value)) {
                    $value = trim($value);
                }
                $studentData[$field] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('admission_no', $studentData)) {
            $value = $studentData['admission_no'];
            if (is_string($value)) {
                $value = trim($value);
            }
            $studentData['admission_no'] = $value === '' ? null : $value;
        }

        // Duplicate-name blocking is temporarily disabled.

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('students/photos', 'public');
            $studentData['photo_url'] = $this->formatStoredFileUrl($photoPath);
        } elseif (array_key_exists('photo_url', $studentData) && ! $studentData['photo_url']) {
            $studentData['photo_url'] = null;
        }

        $student = DB::transaction(function () use ($studentData, $school, $session) {
            $payload = $studentData;
            if (! array_key_exists('admission_no', $payload) || ! $payload['admission_no']) {
                $payload['admission_no'] = Student::generateAdmissionNumber($school, $session);
            }

            return Student::create($payload);
        });

        return response()->json([
            'data' => $student->load($this->studentRelations()),
        ], 201);
    }

    public function regenerateAdmissionNumbers(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = strtolower(trim((string) ($user->role ?? '')));
        $isAdmin = in_array($role, ['admin', 'super_admin', 'superadmin', 'administrator'], true)
            || $user->hasAnyRole(['admin', 'super_admin']);

        if (! $isAdmin) {
            abort(403, 'Only administrators can regenerate admission numbers.');
        }

        $this->ensurePermission($request, ['students.update', 'students.edit']);

        $school = $user->school;
        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'student_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('students', 'id')->where('school_id', $school->id),
            ],
        ]);

        $regenerated = DB::transaction(function () use ($validated, $school) {
            $students = Student::query()
                ->where('school_id', $school->id)
                ->whereIn('id', $validated['student_ids'])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            return collect($validated['student_ids'])->map(function (string $studentId) use ($students, $school) {
                /** @var Student $student */
                $student = $students->get($studentId);
                $session = \App\Models\Session::query()
                    ->where('school_id', $school->id)
                    ->find($student->current_session_id);

                if (! $session) {
                    abort(422, "A current session is required to regenerate {$student->first_name} {$student->last_name}'s admission number.");
                }

                $previousAdmissionNo = $student->admission_no;
                $student->admission_no = Student::generateAdmissionNumber($school, $session);
                $student->save();

                return [
                    'id' => $student->id,
                    'previous_admission_no' => $previousAdmissionNo,
                    'admission_no' => $student->admission_no,
                ];
            })->values();
        });

        $count = $regenerated->count();

        return response()->json([
            'message' => "Regenerated admission numbers for {$count} student".($count === 1 ? '.' : 's.'),
            'data' => $regenerated,
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Student  $student
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Get(
     *      path="/api/v1/students/{id}",
     *      operationId="getStudentById",
     *      tags={"school-v1.4","school-v1.9","school-v2.0"},
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
        $scope = $this->teacherAccess->forUser($request->user());
        if (! $scope->isTeacher()) {
            $this->ensurePermission($request, 'students.view');
        }
        Student::fixLegacyForeignKeys();
        if ($student->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        if ($scope->isTeacher() && ! $scope->allowsClassTeacherStudent($student)) {
            abort(403, 'You are not allowed to view this student.');
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
     *      path="/api/v1/students/{id}",
     *      operationId="updateStudent",
     *      tags={"school-v1.4","school-v1.9","school-v2.0"},
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
     *              @OA\Property(property="admission_no", type="string", example="NC001-2024/2025/1"),
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
        $this->ensurePermission($request, ['students.update', 'students.edit']);
        Student::fixLegacyForeignKeys();
        if ($student->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $scope = $this->teacherAccess->forUser($request->user());

        if ($scope->isTeacher() && ! $scope->allowsStudent($student)) {
            abort(403, 'You are not allowed to update this student.');
        }

        $this->prepareRelationshipInput($request);

        $validated = $request->validate([
            'admission_no' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('students', 'admission_no')
                    ->ignore($student->id),
            ],
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
            'class_arm_id' => 'nullable|exists:class_arms,id',
            'parent_id' => 'nullable|exists:parents,id',
            'admission_date' => 'required|date',
            'photo_url' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(['active', 'inactive', 'graduated', 'withdrawn'])],
        ]);

        $validated['class_section_id'] = null;

        if (array_key_exists('parent_id', $validated) && ! $validated['parent_id']) {
            $validated['parent_id'] = null;
        }
        if (array_key_exists('class_arm_id', $validated) && ! $validated['class_arm_id']) {
            $validated['class_arm_id'] = null;
        }

        foreach (['house', 'club'] as $field) {
            if (array_key_exists($field, $validated)) {
                $value = $validated[$field];
                if (is_string($value)) {
                    $value = trim($value);
                }
                $validated[$field] = $value === '' ? null : $value;
            }
        }

        // Duplicate-name blocking is temporarily disabled.

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('students/photos', 'public');
            if ($student->photo_url) {
                $this->deletePublicFile($student->photo_url);
            }
            $validated['photo_url'] = $this->formatStoredFileUrl($photoPath);
        } elseif (array_key_exists('photo_url', $validated) && ! $validated['photo_url']) {
            if ($student->photo_url) {
                $this->deletePublicFile($student->photo_url);
            }
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
     * Reset a student's portal password to the school default.
     */
    public function resetPortalPassword(Request $request, Student $student): JsonResponse
    {
        $this->ensurePermission($request, ['students.update', 'students.edit']);

        if ($student->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $student->update([
            'portal_password' => '123456',
            'portal_password_changed_at' => now(),
        ]);

        $student->tokens()->delete();

        return response()->json([
            'message' => 'Student password reset to 123456 successfully.',
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
     *      path="/api/v1/students/{id}/dependent-records",
     *      operationId="deleteStudentDependentRecords",
     *      tags={"school-v1.4","school-v1.9","school-v2.0"},
     *      summary="Delete all dependent records for a student",
     *      description="Deletes all dependent records before deleting the student",
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
     *          description="Successfully deleted dependent records",
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
    public function deleteDependentRecords(Request $request, Student $student)
    {
        $this->ensurePermission($request, 'students.delete');

        if ($student->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $scope = $this->teacherAccess->forUser($request->user());

        if ($scope->isTeacher() && ! $scope->allowsStudent($student)) {
            abort(403, 'You are not allowed to delete records for this student.');
        }

        $deletedCounts = [];

        DB::transaction(function () use ($student, &$deletedCounts) {
            $tables = [
                'results',
                'attendances',
                'fee_payments',
                'performance_reports',
                'result_pins',
                'skill_ratings',
                'student_enrollments',
                'term_summaries',
                'promotion_logs',
                'quiz_results',
                'quiz_attempts',
                'cbt_score_imports',
            ];

            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $deletedCounts[$table] = DB::table($table)
                    ->where('student_id', $student->id)
                    ->delete();
            }
        });

        return response()->json([
            'message' => 'Successfully deleted all dependent records.',
            'deleted_counts' => $deletedCounts,
        ]);
    }

    /**
     * @OA\Delete(
     *      path="/api/v1/students/{id}",
     *      operationId="deleteStudent",
     *      tags={"school-v1.4","school-v1.9","school-v2.0"},
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
        $this->ensurePermission($request, 'students.delete');
        Student::fixLegacyForeignKeys();
        if ($student->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $scope = $this->teacherAccess->forUser($request->user());

        if ($scope->isTeacher() && ! $scope->allowsStudent($student)) {
            abort(403, 'You are not allowed to delete this student.');
        }

        $dependencies = $this->studentDeletionDependencies($student->id);
        if ($dependencies !== []) {
            return response()->json([
                'message' => 'Cannot delete student with dependent records. Remove related records first.',
                'dependencies' => $dependencies,
            ], 422);
        }

        $photoUrl = $student->photo_url;

        DB::transaction(function () use ($student) {
            $student->delete();
        });

        if ($photoUrl) {
            $this->deletePublicFile($photoUrl);
        }

        return response()->json(null, 204);
    }


    protected function studentRelations(): array
    {
        return ['school_class', 'class_arm', 'parent', 'session', 'term', 'blood_group'];
    }

    protected function prepareRelationshipInput(Request $request): void
    {
        $classIdentifier = $request->input('school_class_id', $request->input('class_id'));

        if ($this->isNullableRelationshipValue($classIdentifier)) {
            $request->request->remove('school_class_id');
        } else {
            $request->merge(['school_class_id' => trim((string) $classIdentifier)]);
        }

        foreach (['school_class_id', 'class_arm_id', 'parent_id', 'current_session_id', 'current_term_id', 'blood_group_id'] as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $value = $request->input($field);

            if ($this->isNullableRelationshipValue($value)) {
                $request->merge([$field => null]);
            } else {
                $request->merge([$field => trim((string) $value)]);
            }
        }
    }

    private function isNullableRelationshipValue(mixed $value): bool
    {
        if (in_array($value, [null, '', '0', 0], true)) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['none', 'null', 'undefined'], true);
    }

    private function studentDeletionDependencies(string $studentId): array
    {
        $checks = [
            'results' => 'result records',
            'attendances' => 'attendance records',
            'fee_payments' => 'fee payment records',
            'performance_reports' => 'performance report records',
            'result_pins' => 'result pin records',
            'skill_ratings' => 'skill rating records',
            'student_enrollments' => 'enrollment records',
            'term_summaries' => 'term summary records',
            'promotion_logs' => 'promotion log records',
            'quiz_results' => 'quiz result records',
            'quiz_attempts' => 'quiz attempt records',
            'cbt_score_imports' => 'CBT score import records',
        ];

        $dependencies = [];

        foreach ($checks as $table => $label) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (DB::table($table)->where('student_id', $studentId)->exists()) {
                $dependencies[] = $label;
            }
        }

        return $dependencies;
    }

    private function formatStoredFileUrl(string $path): string
    {
        return Storage::disk('public')->url($path); // returns value like /storage/...
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

    private function findDuplicateStudent(string $schoolId, array $studentData, ?string $excludeStudentId = null): ?Student
    {
        $firstName = strtolower(trim((string) ($studentData['first_name'] ?? '')));
        $lastName = strtolower(trim((string) ($studentData['last_name'] ?? '')));

        if ($firstName === '' || $lastName === '') {
            return null;
        }

        return Student::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->when($excludeStudentId, function ($query) use ($excludeStudentId) {
                $query->where('id', '!=', $excludeStudentId);
            })
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [$firstName])
            ->whereRaw('LOWER(TRIM(last_name)) = ?', [$lastName])
            ->first();
    }

    private function duplicateStudentResponse(Student $student): JsonResponse
    {
        return response()->json([
            'message' => 'A student with the same first and last name already exists.',
            'is_duplicate' => true,
            'duplicate' => [
                'id' => $student->id,
                'admission_no' => $student->admission_no,
                'name' => trim("{$student->first_name} {$student->last_name}"),
                'match' => 'name',
            ],
        ], 409);
    }
}
