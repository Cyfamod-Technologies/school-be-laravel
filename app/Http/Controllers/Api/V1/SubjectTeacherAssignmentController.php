<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\ClassSection;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\SubjectTeacherAssignment;
use App\Models\Term;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubjectTeacherAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'subject.assignments.teacher');
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

        $query = SubjectTeacherAssignment::query()
            ->with([
                'subject:id,name,code,school_id',
                'staff:id,full_name,email,phone,role,school_id',
                'school_class:id,name,slug,school_id',
                'class_arm:id,name,school_class_id',
                'class_section:id,name,class_arm_id',
                'session:id,name,school_id',
                'term:id,name,school_id,session_id',
            ])
            ->whereHas('subject', fn (Builder $builder) => $builder->where('school_id', $school->id));

        $filters = [
            'subject_id',
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
                $builder->whereHas('subject', function (Builder $subjectQuery) use ($search) {
                    $subjectQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })->orWhereHas('staff', function (Builder $staffQuery) use ($search) {
                    $staffQuery->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
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
        $this->ensurePermission($request, 'subject.assignments.teacher');
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'subject_id' => ['required', 'uuid'],
            'staff_id' => ['required', 'uuid'],
            'school_class_id' => ['nullable', 'uuid'],
            'class_arm_id' => ['nullable', 'uuid'],
            'class_section_id' => ['nullable', 'uuid'],
            'session_id' => ['required', 'uuid'],
            'term_id' => ['required', 'uuid'],
        ]);

        $entities = $this->resolveEntities($school->id, $validated);

        if ($this->teacherAssignmentExists($entities, null)) {
            return response()->json([
                'message' => 'Teacher is already assigned to this subject for the selected context.',
            ], 422);
        }

        $assignment = SubjectTeacherAssignment::create([
            'id' => (string) Str::uuid(),
            'subject_id' => $entities['subject']->id,
            'staff_id' => $entities['staff']->id,
            'school_class_id' => $entities['class']?->id,
            'class_arm_id' => $entities['class_arm']?->id,
            'class_section_id' => $entities['class_section']?->id,
            'session_id' => $entities['session']->id,
            'term_id' => $entities['term']->id,
        ]);

        return response()->json([
            'message' => 'Teacher assigned successfully.',
            'data' => $assignment->load([
                'subject:id,name,code',
                'staff:id,full_name,email,phone,role',
                'school_class:id,name',
                'class_arm:id,name',
                'class_section:id,name',
                'session:id,name',
                'term:id,name',
            ]),
        ], 201);
    }

    public function show(Request $request, SubjectTeacherAssignment $assignment)
    {
        $this->authorizeTeacherAssignment($request, $assignment);

        return response()->json([
            'id' => $assignment->id,
            'subject_id' => $assignment->subject_id,
            'staff_id' => $assignment->staff_id,
            'school_class_id' => $assignment->school_class_id,
            'class_arm_id' => $assignment->class_arm_id,
            'class_section_id' => $assignment->class_section_id,
            'session_id' => $assignment->session_id,
            'term_id' => $assignment->term_id,
            'subject' => optional($assignment->subject)->only(['id','name','code']),
            'staff' => optional($assignment->staff)->only(['id','full_name','email','phone','role']),
            'school_class' => optional($assignment->school_class)->only(['id','name']),
            'class_arm' => optional($assignment->class_arm)->only(['id','name']),
            'class_section' => optional($assignment->class_section)->only(['id','name']),
            'session' => optional($assignment->session)->only(['id','name']),
            'term' => optional($assignment->term)->only(['id','name']),
        ]);
    }

    public function update(Request $request, SubjectTeacherAssignment $assignment)
    {
        $this->ensurePermission($request, 'subject.assignments.teacher');
        $this->authorizeTeacherAssignment($request, $assignment);

        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'subject_id' => ['sometimes', 'required', 'uuid'],
            'staff_id' => ['sometimes', 'required', 'uuid'],
            'school_class_id' => ['nullable', 'uuid'],
            'class_arm_id' => ['nullable', 'uuid'],
            'class_section_id' => ['nullable', 'uuid'],
            'session_id' => ['sometimes', 'required', 'uuid'],
            'term_id' => ['sometimes', 'required', 'uuid'],
        ]);

        $payload = [
            'subject_id' => $validated['subject_id'] ?? $assignment->subject_id,
            'staff_id' => $validated['staff_id'] ?? $assignment->staff_id,
            'school_class_id' => $validated['school_class_id'] ?? $assignment->school_class_id,
            'class_arm_id' => $validated['class_arm_id'] ?? $assignment->class_arm_id,
            'class_section_id' => array_key_exists('class_section_id', $validated)
                ? $validated['class_section_id']
                : $assignment->class_section_id,
            'session_id' => $validated['session_id'] ?? $assignment->session_id,
            'term_id' => $validated['term_id'] ?? $assignment->term_id,
        ];

        $entities = $this->resolveEntities($school->id, $payload);

        if ($this->teacherAssignmentExists($entities, $assignment->id)) {
            return response()->json([
                'message' => 'Teacher is already assigned to this subject for the selected context.',
            ], 422);
        }

        $assignment->fill([
            'subject_id' => $entities['subject']->id,
            'staff_id' => $entities['staff']->id,
            'school_class_id' => $entities['class']?->id,
            'class_arm_id' => $entities['class_arm']?->id,
            'class_section_id' => $entities['class_section']?->id,
            'session_id' => $entities['session']->id,
            'term_id' => $entities['term']->id,
        ]);

        if ($assignment->isDirty()) {
            $assignment->save();
        }

        return response()->json([
            'message' => 'Teacher assignment updated successfully.',
            'data' => $assignment->fresh()->load([
                'subject:id,name,code',
                'staff:id,full_name,email,phone,role',
                'school_class:id,name',
                'class_arm:id,name',
                'class_section:id,name',
                'session:id,name',
                'term:id,name',
            ]),
        ]);
    }

    public function destroy(Request $request, SubjectTeacherAssignment $assignment)
    {
        $this->ensurePermission($request, 'subject.assignments.teacher');
        $this->authorizeTeacherAssignment($request, $assignment);
        $assignment->delete();

        return response()->json([
            'message' => 'Teacher assignment removed successfully.',
        ]);
    }

    private function resolveEntities(string $schoolId, array $payload): array
    {
        $subject = Subject::where('id', $payload['subject_id'])
            ->where('school_id', $schoolId)
            ->first();

        if (! $subject) {
            abort(404, 'Subject not found for the authenticated school.');
        }

        $class = null;
        if (! empty($payload['school_class_id'])) {
            $class = SchoolClass::where('id', $payload['school_class_id'])
                ->where('school_id', $schoolId)
                ->first();

            if (! $class) {
                abort(404, 'Class not found for the authenticated school.');
            }
        }

        $classArm = null;
        if (! empty($payload['class_arm_id'])) {
            $armQuery = ClassArm::where('id', $payload['class_arm_id']);

            if ($class) {
                $armQuery->where('school_class_id', $class->id);
            }

            $classArm = $armQuery->first();

            if (! $classArm) {
                abort(404, 'Class arm not found or does not belong to the selected class.');
            }
        }

        $classSection = null;
        if (! empty($payload['class_section_id'])) {
            $sectionQuery = ClassSection::where('id', $payload['class_section_id']);

            if ($classArm) {
                $sectionQuery->where('class_arm_id', $classArm->id);
            }

            $classSection = $sectionQuery->first();

            if (! $classSection) {
                abort(404, 'Class section not found or does not belong to the selected class arm.');
            }
        }

        $staff = Staff::where('id', $payload['staff_id'])
            ->where('school_id', $schoolId)
            ->first();

        if (! $staff) {
            abort(404, 'Staff not found for the authenticated school.');
        }

        if (! $this->isTeacherRole($staff->role ?? '')) {
            abort(422, 'Selected staff member is not eligible to be assigned as a teacher.');
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
            'subject' => $subject,
            'staff' => $staff,
            'class' => $class,
            'class_arm' => $classArm,
            'class_section' => $classSection,
            'session' => $session,
            'term' => $term,
        ];
    }

    private function teacherAssignmentExists(array $entities, ?string $ignoreId): bool
    {
        $query = SubjectTeacherAssignment::query()
            ->where('subject_id', $entities['subject']->id)
            ->where('staff_id', $entities['staff']->id)
            ->when($entities['class'], fn (Builder $builder, SchoolClass $class) => $builder->where('school_class_id', $class->id), fn (Builder $builder) => $builder->whereNull('school_class_id'))
            ->when($entities['class_arm'], fn (Builder $builder, ClassArm $arm) => $builder->where('class_arm_id', $arm->id), fn (Builder $builder) => $builder->whereNull('class_arm_id'))
            ->when($entities['class_section'], fn (Builder $builder, ClassSection $section) => $builder->where('class_section_id', $section->id), fn (Builder $builder) => $builder->whereNull('class_section_id'))
            ->where('session_id', $entities['session']->id)
            ->where('term_id', $entities['term']->id);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function authorizeTeacherAssignment(Request $request, SubjectTeacherAssignment $assignment): void
    {
        $schoolId = optional($request->user()->school)->id;
        $assignment->loadMissing('subject');

        abort_unless($schoolId && optional($assignment->subject)->school_id === $schoolId, 404);
    }

    private function isTeacherRole(string $role): bool
    {
        return str_contains(strtolower($role), 'teach');
    }
}
