<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'subjects.view');
        $perPage = max((int) $request->input('per_page', 15), 1);
        $allowedSorts = ['name', 'code', 'created_at'];
        $sortBy = $request->input('sortBy', 'name');
        $sortDirection = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'name';
        }

        $subjects = Subject::query()
            ->where('school_id', $request->user()->school_id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($subjects);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'subjects.create');
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects', 'name')->where(fn ($query) => $query->where('school_id', $school->id)),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('subjects', 'code')->where(fn ($query) => $query->where('school_id', $school->id)),
            ],
            'description' => ['nullable', 'string'],
        ]);

        $subject = Subject::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Subject created successfully.',
            'data' => $subject,
        ], 201);
    }

    public function show(Request $request, Subject $subject)
    {
        $this->authorizeSubjectAccess($request, $subject);

        return response()->json([
            'data' => $subject,
        ]);
    }

    public function update(Request $request, Subject $subject)
    {
        $this->ensurePermission($request, 'subjects.update');
        $this->authorizeSubjectAccess($request, $subject);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('subjects', 'name')
                    ->where(fn ($query) => $query->where('school_id', $subject->school_id))
                    ->ignore($subject->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('subjects', 'code')
                    ->where(fn ($query) => $query->where('school_id', $subject->school_id))
                    ->ignore($subject->id),
            ],
            'description' => ['nullable', 'string'],
        ]);

        $updates = [];

        if (array_key_exists('name', $validated)) {
            $updates['name'] = $validated['name'];
        }
        if (array_key_exists('code', $validated)) {
            $updates['code'] = $validated['code'];
        }
        if (array_key_exists('description', $validated)) {
            $updates['description'] = $validated['description'];
        }

        if (! empty($updates)) {
            $subject->fill($updates);

            if ($subject->isDirty()) {
                $subject->save();
            }
        }

        return response()->json([
            'message' => 'Subject updated successfully.',
            'data' => $subject->fresh(),
        ]);
    }

    public function destroy(Request $request, Subject $subject)
    {
        $this->ensurePermission($request, 'subjects.delete');
        $this->authorizeSubjectAccess($request, $subject);

        $hasTeacherAssignments = $subject->subject_teacher_assignments()->exists();
        $hasClassAssignments = $subject->school_classes()->exists();
        $hasResults = $subject->results()->exists();

        if ($hasTeacherAssignments || $hasClassAssignments || $hasResults) {
            return response()->json([
                'message' => 'Cannot delete subject with existing assignments or results.',
            ], 422);
        }

        $subject->delete();

        return response()->json([
            'message' => 'Subject deleted successfully.',
        ]);
    }

    protected function authorizeSubjectAccess(Request $request, Subject $subject): void
    {
        abort_unless($subject->school_id === $request->user()->school_id, 404);
    }
}
