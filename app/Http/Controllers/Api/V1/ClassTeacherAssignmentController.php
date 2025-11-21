<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\ClassSection;
use App\Models\ClassTeacher;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Staff;
use App\Models\Term;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClassTeacherAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'class-teachers.manage');
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $perPage = max((int) $request->input('per_page', 15), 1);
        $sortBy = $request->input('sortBy', 'created_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['created_at', 'updated_at'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $query = ClassTeacher::query()
            ->with([
                'staff:id,full_name,email,phone,role,school_id',
                'school_class:id,name,slug,school_id',
                'class_arm:id,name,school_class_id',
                'class_section:id,name,class_arm_id',
                'session:id,name,school_id',
                'term:id,name,school_id,session_id',
            ])
            ->whereHas('staff', fn (Builder $builder) => $builder->where('school_id', $school->id));

        $filters = [
            'staff_id',
            'school_class_id',
            'class_arm_id',
            'class_section_id',
            'session_id',
            'term_id',
        ];

        foreach ($filters as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $builder) use ($search) {
                $builder->whereHas('staff', function (Builder $staffQuery) use ($search) {
                    $staffQuery->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })->orWhereHas('school_class', function (Builder $classQuery) use ($search) {
                    $classQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        $assignments = $query->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($assignments);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'class-teachers.manage');
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'staff_id' => ['required', 'uuid'],
            'school_class_id' => ['required', 'uuid'],
            'class_arm_id' => ['required', 'uuid'],
            'class_section_id' => ['nullable', 'uuid'],
            'session_id' => ['required', 'uuid'],
            'term_id' => ['required', 'uuid'],
        ]);

        $entities = $this->resolveEntities($school->id, $validated);

        if ($this->assignmentExists($entities, null)) {
            return response()->json([
                'message' => 'A teacher is already assigned to the selected class context.',
            ], 422);
        }

        $assignment = ClassTeacher::create([
            'id' => (string) Str::uuid(),
            'staff_id' => $entities['staff']->id,
            'school_class_id' => $entities['class']->id,
            'class_arm_id' => $entities['class_arm']->id,
            'class_section_id' => $entities['class_section']?->id,
            'session_id' => $entities['session']->id,
            'term_id' => $entities['term']->id,
        ]);

        return response()->json([
            'message' => 'Class teacher assigned successfully.',
            'data' => $assignment->load([
                'staff:id,full_name,email,phone,role',
                'school_class:id,name',
                'class_arm:id,name',
                'class_section:id,name',
                'session:id,name',
                'term:id,name',
            ]),
        ], 201);
    }

    public function show(Request $request, ClassTeacher $classTeacher)
    {
        $this->ensurePermission($request, 'class-teachers.manage');
        $this->authorizeAssignment($request, $classTeacher);

        return response()->json([
            'id' => $classTeacher->id,
            'staff_id' => $classTeacher->staff_id,
            'school_class_id' => $classTeacher->school_class_id,
            'class_arm_id' => $classTeacher->class_arm_id,
            'class_section_id' => $classTeacher->class_section_id,
            'session_id' => $classTeacher->session_id,
            'term_id' => $classTeacher->term_id,
            'staff' => optional($classTeacher->staff)->only(['id', 'full_name', 'email', 'phone']),
            'school_class' => optional($classTeacher->school_class)->only(['id', 'name']),
            'class_arm' => optional($classTeacher->class_arm)->only(['id', 'name']),
            'class_section' => optional($classTeacher->class_section)->only(['id', 'name']),
            'session' => optional($classTeacher->session)->only(['id', 'name']),
            'term' => optional($classTeacher->term)->only(['id', 'name']),
        ]);
    }

    public function update(Request $request, ClassTeacher $classTeacher)
    {
        $this->ensurePermission($request, 'class-teachers.manage');
        $this->authorizeAssignment($request, $classTeacher);

        $validated = $request->validate([
            'staff_id' => ['sometimes', 'required', 'uuid'],
            'school_class_id' => ['sometimes', 'required', 'uuid'],
            'class_arm_id' => ['sometimes', 'required', 'uuid'],
            'class_section_id' => ['nullable', 'uuid'],
            'session_id' => ['sometimes', 'required', 'uuid'],
            'term_id' => ['sometimes', 'required', 'uuid'],
        ]);

        $payload = [
            'staff_id' => $validated['staff_id'] ?? $classTeacher->staff_id,
            'school_class_id' => $validated['school_class_id'] ?? $classTeacher->school_class_id,
            'class_arm_id' => $validated['class_arm_id'] ?? $classTeacher->class_arm_id,
            'class_section_id' => array_key_exists('class_section_id', $validated)
                ? $validated['class_section_id']
                : $classTeacher->class_section_id,
            'session_id' => $validated['session_id'] ?? $classTeacher->session_id,
            'term_id' => $validated['term_id'] ?? $classTeacher->term_id,
        ];

        $entities = $this->resolveEntities($request->user()->school->id, $payload);

        if ($this->assignmentExists($entities, $classTeacher->id)) {
            return response()->json([
                'message' => 'A teacher is already assigned to the selected class context.',
            ], 422);
        }

        $classTeacher->fill([
            'staff_id' => $entities['staff']->id,
            'school_class_id' => $entities['class']->id,
            'class_arm_id' => $entities['class_arm']->id,
            'class_section_id' => $entities['class_section']?->id,
            'session_id' => $entities['session']->id,
            'term_id' => $entities['term']->id,
        ]);

        if ($classTeacher->isDirty()) {
            $classTeacher->save();
        }

        return response()->json([
            'message' => 'Class teacher assignment updated successfully.',
            'data' => $classTeacher->fresh()->load([
                'staff:id,full_name,email,phone,role',
                'school_class:id,name',
                'class_arm:id,name',
                'class_section:id,name',
                'session:id,name',
                'term:id,name',
            ]),
        ]);
    }

    public function destroy(Request $request, ClassTeacher $classTeacher)
    {
        $this->ensurePermission($request, 'class-teachers.manage');
        $this->authorizeAssignment($request, $classTeacher);
        $classTeacher->delete();

        return response()->json([
            'message' => 'Class teacher assignment removed successfully.',
        ]);
    }

    private function resolveEntities(string $schoolId, array $payload): array
    {
        $staff = Staff::where('id', $payload['staff_id'])
            ->where('school_id', $schoolId)
            ->first();

        if (! $staff) {
            abort(404, 'Staff not found for the authenticated school.');
        }

        $class = SchoolClass::where('id', $payload['school_class_id'])
            ->where('school_id', $schoolId)
            ->first();

        if (! $class) {
            abort(404, 'Class not found for the authenticated school.');
        }

        $classArm = ClassArm::where('id', $payload['class_arm_id'])
            ->where('school_class_id', $class->id)
            ->first();

        if (! $classArm) {
            abort(404, 'Class arm not found or does not belong to the selected class.');
        }

        $classSection = null;
        if (! empty($payload['class_section_id'])) {
            $classSection = ClassSection::where('id', $payload['class_section_id'])
                ->where('class_arm_id', $classArm->id)
                ->first();

            if (! $classSection) {
                abort(404, 'Class section not found or does not belong to the selected class arm.');
            }
        }

        $session = Session::where('id', $payload['session_id'])
            ->where('school_id', $schoolId)
            ->first();

        if (! $session) {
            abort(404, 'Session not found for the authenticated school.');
        }

        $term = Term::where('id', $payload['term_id'])
            ->where('school_id', $schoolId)
            ->first();

        if (! $term) {
            abort(404, 'Term not found for the authenticated school.');
        }

        if ($term->session_id !== $session->id) {
            abort(422, 'Term must belong to the selected session.');
        }

        return [
            'staff' => $staff,
            'class' => $class,
            'class_arm' => $classArm,
            'class_section' => $classSection,
            'session' => $session,
            'term' => $term,
        ];
    }

    private function assignmentExists(array $entities, ?string $ignoreId): bool
    {
        $query = ClassTeacher::query()
            ->where('staff_id', $entities['staff']->id)
            ->where('school_class_id', $entities['class']->id)
            ->where('class_arm_id', $entities['class_arm']->id)
            ->when($entities['class_section'], fn (Builder $builder, ClassSection $section) => $builder->where('class_section_id', $section->id), fn (Builder $builder) => $builder->whereNull('class_section_id'))
            ->where('session_id', $entities['session']->id)
            ->where('term_id', $entities['term']->id);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function authorizeAssignment(Request $request, ClassTeacher $classTeacher): void
    {
        $schoolId = optional($request->user()->school)->id;
        $classTeacher->loadMissing('staff');

        abort_unless($schoolId && optional($classTeacher->staff)->school_id === $schoolId, 404);
    }
}
