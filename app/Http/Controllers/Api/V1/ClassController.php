<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\SchoolClass;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="school-v1.2",
 *     description="Class & Arm Setup"
 * )
 */
class ClassController extends Controller
{
    /**
     * @OA\Get(
     *      path="/v1/classes",
     *      operationId="getClassesList",
     *      tags={"school-v1.2"},
     *      summary="Get list of classes",
     *      description="Returns list of classes",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       )
     *     )
     */
    public function index()
    {
        $this->ensurePermission(request(), 'classes.manage');
        $schoolId = auth()->user()->school_id;
        $classes = SchoolClass::where('school_id', $schoolId)->get();

        return response()->json($classes);
    }

    /**
     * @OA\Post(
     *      path="/v1/classes",
     *      operationId="storeClass",
     *      tags={"school-v1.2"},
     *      summary="Store new class",
     *      description="Stores a new class and returns the created class",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name", "school_id"},
     *              @OA\Property(property="name", type="string", example="JSS1"),
     *              @OA\Property(property="school_id", type="string", format="uuid", example="9a7a7b0e-0b1c-4f0e-8c1a-0b1c4f0e8c1a")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation"
     *       )
     * )
     */
    public function store(Request $request)
    {
        $this->ensurePermission($request, ['classes.create', 'classes.manage']);
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classes')->where(function ($query) use ($request) {
                    return $query->where('school_id', $request->school_id);
                }),
            ],
            'school_id' => 'required|exists:schools,id',
        ]);

        $class = SchoolClass::create([
            'id' => Str::uuid(),
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'school_id' => $request->school_id,
        ]);

        return response()->json($class, 201);
    }

    /**
     * @OA\Get(
     *      path="/v1/classes/{id}",
     *      operationId="getClassById",
     *      tags={"school-v1.2"},
     *      summary="Get session information",
     *      description="Returns class data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Class id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       )
     * )
     */
    public function show(SchoolClass $schoolClass)
    {
        $this->ensurePermission(request(), 'classes.manage');
        return $schoolClass;
    }

    /**
     * @OA\Put(
     *      path="/v1/classes/{id}",
     *      operationId="updateClass",
     *      tags={"school-v1.2"},
     *      summary="Update existing class",
     *      description="Updates a class and returns the updated class",
     *      @OA\Parameter(
     *          name="id",
     *          description="Class id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name"},
     *              @OA\Property(property="name", type="string", example="JSS1")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       )
     * )
     */
    public function update(Request $request, SchoolClass $schoolClass)
    {
        $this->ensurePermission($request, ['classes.update', 'classes.manage']);
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classes')->where(function ($query) use ($request, $schoolClass) {
                    return $query->where('school_id', $schoolClass->school_id)->where('id', '!=', $schoolClass->id);
                }),
            ],
        ]);

        $schoolClass->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json($schoolClass);
    }

    /**
     * @OA\Delete(
     *      path="/v1/classes/{id}",
     *      operationId="deleteClass",
     *      tags={"school-v1.2"},
     *      summary="Delete existing class",
     *      description="Deletes a class and returns no content",
     *      @OA\Parameter(
     *          name="id",
     *          description="Class id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=204,
     *          description="Successful operation"
     *       )
     * )
     */
    public function destroy(SchoolClass $schoolClass)
    {
        $this->ensurePermission(request(), ['classes.delete', 'classes.manage']);
        if ($schoolClass->class_arms()->exists() || $schoolClass->students()->exists()) {
            return response()->json(['error' => 'Cannot delete class with associated arms or students.'], 422);
        }

        $schoolClass->delete();

        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *      path="/v1/classes/{classId}/arms",
     *      operationId="getClassArmsList",
     *      tags={"school-v1.2"},
     *      summary="Get list of class arms",
     *      description="Returns list of class arms for a given class",
     *      @OA\Parameter(
     *          name="classId",
     *          description="Class id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       )
     *     )
     */
    public function indexArms(SchoolClass $schoolClass)
    {
        $this->ensurePermission(request(), 'classes.manage');
        return $schoolClass->class_arms;
    }

    /**
     * @OA\Post(
     *      path="/v1/classes/{classId}/arms",
     *      operationId="storeClassArm",
     *      tags={"school-v1.2"},
     *      summary="Store new class arm",
     *      description="Stores a new class arm and returns the created class arm",
     *      @OA\Parameter(
     *          name="classId",
     *          description="Class id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name"},
     *              @OA\Property(property="name", type="string", example="A")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation"
     *       )
     * )
     */
    public function storeArm(Request $request, SchoolClass $schoolClass)
    {
        $this->ensurePermission($request, ['class-arms.create', 'classes.manage']);
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('class_arms')->where(function ($query) use ($schoolClass) {
                    return $query->where('school_class_id', $schoolClass->id);
                }),
            ],
        ]);

        $arm = $schoolClass->class_arms()->create([
            'id' => Str::uuid(),
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json($arm, 201);
    }

    /**
     * @OA\Get(
     *      path="/v1/classes/{classId}/arms/{armId}",
     *      operationId="getClassArmById",
     *      tags={"school-v1.2"},
     *      summary="Get class arm information",
     *      description="Returns class arm data",
     *      @OA\Parameter(
     *          name="classId",
     *          description="Class id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="armId",
     *          description="Class Arm id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       )
     * )
     */
    public function showArm(SchoolClass $schoolClass, string $armId)
    {
        $this->ensurePermission(request(), 'classes.manage');
        return $schoolClass->class_arms()->findOrFail($armId);
    }

    /**
     * @OA\Put(
     *      path="/v1/classes/{classId}/arms/{armId}",
     *      operationId="updateClassArm",
     *      tags={"school-v1.2"},
     *      summary="Update existing class arm",
     *      description="Updates a class arm and returns the updated class arm",
     *      @OA\Parameter(
     *          name="classId",
     *          description="Class id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="armId",
     *          description="Class Arm id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name"},
     *              @OA\Property(property="name", type="string", example="A"),
     *              @OA\Property(property="color", type="string", example="Blue")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       )
     * )
     */
    public function updateArm(Request $request, SchoolClass $schoolClass, string $armId)
    {
        $this->ensurePermission($request, ['class-arms.update', 'classes.manage']);
        $arm = $schoolClass->class_arms()->findOrFail($armId);

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('class_arms')->where(function ($query) use ($schoolClass, $arm) {
                    return $query->where('school_class_id', $schoolClass->id)->where('id', '!=', $arm->id);
                }),
            ],
        ]);

        $arm->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json($arm);
    }

    /**
     * @OA\Delete(
     *      path="/v1/classes/{classId}/arms/{armId}",
     *      operationId="deleteClassArm",
     *      tags={"school-v1.2"},
     *      summary="Delete existing class arm",
     *      description="Deletes a class arm and returns no content",
     *      @OA\Parameter(
     *          name="classId",
     *          description="Class id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="armId",
     *          description="Class Arm id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=204,
     *          description="Successful operation"
     *       )
     * )
     */
    public function destroyArm(SchoolClass $schoolClass, string $armId)
    {
        $this->ensurePermission(request(), ['class-arms.delete', 'classes.manage']);
        $arm = $schoolClass->class_arms()->findOrFail($armId);

        if ($arm->students()->exists()) {
            return response()->json(['error' => 'Cannot delete arm with associated students.'], 422);
        }

        $arm->delete();

        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *      path="/v1/classes/{classId}/arms/{armId}/sections",
     *      operationId="getClassArmSectionsList",
     *      tags={"school-v1.2"},
     *      summary="Get list of class arm sections",
     *      description="Returns list of class arm sections for a given class arm",
     *      @OA\Parameter(
     *          name="classId",
     *          description="Class id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="armId",
     *          description="Class Arm id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       )
     *     )
     */
    public function indexSections(SchoolClass $schoolClass, string $armId)
    {
        $arm = $schoolClass->class_arms()->findOrFail($armId);
        return $arm->class_sections;
    }

    /**
     * @OA\Post(
     *      path="/v1/classes/{classId}/arms/{armId}/sections",
     *      operationId="storeClassArmSection",
     *      tags={"school-v1.2"},
     *      summary="Store new class arm section",
     *      description="Stores a new class arm section and returns the created class arm section",
     *      @OA\Parameter(
     *          name="classId",
     *          description="Class id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="armId",
     *          description="Class Arm id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name"},
     *              @OA\Property(property="name", type="string", example="Science")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation"
     *       )
     * )
     */
    public function storeSection(Request $request, SchoolClass $schoolClass, string $armId)
    {
        $arm = $schoolClass->class_arms()->findOrFail($armId);

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('class_sections')->where(function ($query) use ($arm) {
                    return $query->where('class_arm_id', $arm->id);
                }),
            ],
        ]);

        $section = $arm->class_sections()->create([
            'id' => Str::uuid(),
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json($section, 201);
    }

    /**
     * @OA\Get(
     *      path="/v1/classes/{classId}/arms/{armId}/sections/{sectionId}",
     *      operationId="getClassArmSectionById",
     *      tags={"school-v1.2"},
     *      summary="Get class arm section information",
     *      description="Returns class arm section data",
     *      @OA\Parameter(
     *          name="classId",
     *          description="Class id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="armId",
     *          description="Class Arm id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="sectionId",
     *          description="Class Arm Section id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       )
     * )
     */
    public function showSection(SchoolClass $schoolClass, string $armId, string $sectionId)
    {
        $arm = $schoolClass->class_arms()->findOrFail($armId);
        return $arm->class_sections()->findOrFail($sectionId);
    }

    /**
     * @OA\Put(
     *      path="/v1/classes/{classId}/arms/{armId}/sections/{sectionId}",
     *      operationId="updateClassArmSection",
     *      tags={"school-v1.2"},
     *      summary="Update existing class arm section",
     *      description="Updates a class arm section and returns the updated class arm section",
     *      @OA\Parameter(
     *          name="classId",
     *          description="Class id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="armId",
     *          description="Class Arm id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="sectionId",
     *          description="Class Arm Section id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name"},
     *              @OA\Property(property="name", type="string", example="Science")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       )
     * )
     */
    public function updateSection(Request $request, SchoolClass $schoolClass, string $armId, string $sectionId)
    {
        $arm = $schoolClass->class_arms()->findOrFail($armId);
        $section = $arm->class_sections()->findOrFail($sectionId);

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('class_sections')->where(function ($query) use ($arm, $section) {
                    return $query->where('class_arm_id', $arm->id)->where('id', '!=', $section->id);
                }),
            ],
        ]);

        $section->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json($section);
    }

    /**
     * @OA\Delete(
     *      path="/v1/classes/{classId}/arms/{armId}/sections/{sectionId}",
     *      operationId="deleteClassArmSection",
     *      tags={"school-v1.2"},
     *      summary="Delete existing class arm section",
     *      description="Deletes a class arm section and returns no content",
     *      @OA\Parameter(
     *          name="classId",
     *          description="Class id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="armId",
     *          description="Class Arm id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="sectionId",
     *          description="Class Arm Section id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=204,
     *          description="Successful operation"
     *       )
     * )
     */
    public function destroySection(SchoolClass $schoolClass, string $armId, string $sectionId)
    {
        $arm = $schoolClass->class_arms()->findOrFail($armId);
        $section = $arm->class_sections()->findOrFail($sectionId);

        if ($section->students()->exists()) {
            return response()->json(['error' => 'Cannot delete section with associated students.'], 422);
        }

        $section->delete();

        return response()->json(null, 204);
    }
}
