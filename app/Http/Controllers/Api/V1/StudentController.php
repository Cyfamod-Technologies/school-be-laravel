<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     *          name="class_id",
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
        $students = $request->user()->school->students()
            ->with(['class', 'class_arm', 'parent', 'session'])
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('full_name', 'like', '%' . $request->search . '%')
                    ->orWhere('admission_no', 'like', '%' . $request->search . '%');
            })
            ->when($request->has('class_id'), function ($query) use ($request) {
                $query->where('class_id', $request->class_id);
            })
            ->when($request->has('parent_id'), function ($query) use ($request) {
                $query->where('parent_id', $request->parent_id);
            })
            ->paginate(10);

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
     *              @OA\Property(property="full_name", type="string", example="John Doe"),
     *              @OA\Property(property="date_of_birth", type="string", format="date", example="2010-01-01"),
     *              @OA\Property(property="gender", type="string", example="male"),
     *              @OA\Property(property="parent_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="class_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="class_arm_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="class_section_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="session_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
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
        $request->validate([
            'school_id' => 'required|exists:schools,id',
            'admission_no' => 'required|string|max:255|unique:students,admission_no',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'date_of_birth' => 'required|date',
            'nationality' => 'nullable|string|max:255',
            'state_of_origin' => 'nullable|string|max:255',
            'lga_of_origin' => 'nullable|string|max:255',
            'house' => 'nullable|string|max:255',
            'club' => 'nullable|string|max:255',
            'current_session_id' => 'required|exists:sessions,id',
            'current_term_id' => 'required|exists:terms,id',
            'class_id' => 'required|exists:classes,id',
            'class_arm_id' => 'required|exists:class_arms,id',
            'class_section_id' => 'nullable|exists:class_sections,id',
            'parent_id' => 'required|exists:school_parents,id',
            'admission_date' => 'required|date',
            'photo_url' => 'nullable|string|max:255',
            'status' => 'required|string|max:255',
        ]);

        $student = Student::create(array_merge($request->all(), [
            'id' => str()->uuid()
        ]));

        return response()->json($student, 201);
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
        if ($student->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json($student);
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
     *              @OA\Property(property="full_name", type="string", example="John Doe"),
     *              @OA\Property(property="date_of_birth", type="string", format="date", example="2010-01-01"),
     *              @OA\Property(property="gender", type="string", example="male"),
     *              @OA\Property(property="parent_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="class_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="class_arm_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="class_section_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *              @OA\Property(property="session_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
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
        if ($student->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $request->validate([
            'school_id' => 'required|exists:schools,id',
            'admission_no' => 'required|string|max:255|unique:students,admission_no,' . $student->id,
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'date_of_birth' => 'required|date',
            'nationality' => 'nullable|string|max:255',
            'state_of_origin' => 'nullable|string|max:255',
            'lga_of_origin' => 'nullable|string|max:255',
            'house' => 'nullable|string|max:255',
            'club' => 'nullable|string|max:255',
            'current_session_id' => 'required|exists:sessions,id',
            'current_term_id' => 'required|exists:terms,id',
            'class_id' => 'required|exists:classes,id',
            'class_arm_id' => 'required|exists:class_arms,id',
            'class_section_id' => 'nullable|exists:class_sections,id',
            'parent_id' => 'required|exists:school_parents,id',
            'admission_date' => 'required|date',
            'photo_url' => 'nullable|string|max:255',
            'status' => 'required|string|max:255',
        ]);

        $student->update($request->all());

        return response()->json($student);
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
}
