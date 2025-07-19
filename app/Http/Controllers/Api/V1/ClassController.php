<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Class;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClassController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Class::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
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

        $class = Class::create([
            'id' => Str::uuid(),
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'school_id' => $request->school_id,
        ]);

        return response()->json($class, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Class::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $class = Class::findOrFail($id);

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classes')->where(function ($query) use ($request, $class) {
                    return $query->where('school_id', $class->school_id)->where('id', '!=', $class->id);
                }),
            ],
        ]);

        $class->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json($class);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $class = Class::findOrFail($id);

        if ($class->class_arms()->exists() || $class->students()->exists()) {
            return response()->json(['error' => 'Cannot delete class with associated arms or students.'], 422);
        }

        $class->delete();

        return response()->json(null, 204);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexArms(string $classId)
    {
        $class = Class::findOrFail($classId);
        return $class->class_arms;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function storeArm(Request $request, string $classId)
    {
        $class = Class::findOrFail($classId);

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('class_arms')->where(function ($query) use ($class) {
                    return $query->where('class_id', $class->id);
                }),
            ],
        ]);

        $arm = $class->class_arms()->create([
            'id' => Str::uuid(),
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json($arm, 201);
    }

    /**
     * Display the specified resource.
     */
    public function showArm(string $classId, string $armId)
    {
        $class = Class::findOrFail($classId);
        return $class->class_arms()->findOrFail($armId);
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateArm(Request $request, string $classId, string $armId)
    {
        $class = Class::findOrFail($classId);
        $arm = $class->class_arms()->findOrFail($armId);

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('class_arms')->where(function ($query) use ($class, $arm) {
                    return $query->where('class_id', $class->id)->where('id', '!=', $arm->id);
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
     * Remove the specified resource from storage.
     */
    public function destroyArm(string $classId, string $armId)
    {
        $class = Class::findOrFail($classId);
        $arm = $class->class_arms()->findOrFail($armId);

        if ($arm->students()->exists()) {
            return response()->json(['error' => 'Cannot delete arm with associated students.'], 422);
        }

        $arm->delete();

        return response()->json(null, 204);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexSections(string $classId, string $armId)
    {
        $arm = Class::findOrFail($classId)->class_arms()->findOrFail($armId);
        return $arm->class_sections;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function storeSection(Request $request, string $classId, string $armId)
    {
        $arm = Class::findOrFail($classId)->class_arms()->findOrFail($armId);

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
     * Display the specified resource.
     */
    public function showSection(string $classId, string $armId, string $sectionId)
    {
        $arm = Class::findOrFail($classId)->class_arms()->findOrFail($armId);
        return $arm->class_sections()->findOrFail($sectionId);
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateSection(Request $request, string $classId, string $armId, string $sectionId)
    {
        $arm = Class::findOrFail($classId)->class_arms()->findOrFail($armId);
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
     * Remove the specified resource from storage.
     */
    public function destroySection(string $classId, string $armId, string $sectionId)
    {
        $arm = Class::findOrFail($classId)->class_arms()->findOrFail($armId);
        $section = $arm->class_sections()->findOrFail($sectionId);

        if ($section->students()->exists()) {
            return response()->json(['error' => 'Cannot delete section with associated students.'], 422);
        }

        $section->delete();

        return response()->json(null, 204);
    }
}
