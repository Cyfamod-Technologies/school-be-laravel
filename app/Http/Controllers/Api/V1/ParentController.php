<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SchoolParent;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * @OA\Tag(
 *     name="school-v1.3",
 *     description="API for Parent Management "
 * )
 */
class ParentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Get(
     *      path="/v1/parents",
     *      operationId="getParentsList",
     *      tags={"school-v1.3"},
     *      summary="Get list of parents",
     *      description="Returns list of parents",
     *      @OA\Parameter(
     *          name="search",
     *          description="Search by name or phone",
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
        $this->ensurePermission($request, 'parents.view');
        $parents = $request->user()->school->parents()
            ->select([
                'parents.id',
                'parents.user_id',
                'parents.first_name',
                'parents.last_name',
                'parents.phone',
            ])
            ->selectRaw('(
                SELECT COUNT(*)
                FROM students
                WHERE students.parent_id = parents.id
            ) as students_count')
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('first_name', 'like', '%' . $request->search . '%')
                    ->orWhere('last_name', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%');
            })
            ->paginate(10);

        return response()->json($parents);
    }

    public function all(Request $request)
    {
        $this->ensurePermission($request, 'parents.view');
        $parents = $request->user()->school->parents()
            ->with(['user:id,email,school_id'])
            ->select([
                'parents.id',
                'parents.user_id',
                'parents.first_name',
                'parents.last_name',
                'parents.phone',
            ])
            ->selectRaw('(
                SELECT COUNT(*)
                FROM students
                WHERE students.parent_id = parents.id
            ) as students_count')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return response()->json($parents);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Post(
     *      path="/v1/parents",
     *      operationId="storeParent",
     *      tags={"school-v1.3"},
     *      summary="Store new parent",
     *      description="Returns parent data",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="first_name", type="string", example="John"),
     *              @OA\Property(property="last_name", type="string", example="Doe"),
     *              @OA\Property(property="phone", type="string", example="1234567890"),
     *              @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *              @OA\Property(property="address", type="string", example="123 Main St"),
     *              @OA\Property(property="occupation", type="string", example="Engineer"),
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
        $this->ensurePermission($request, ['parents.create', 'parents.manage']);
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|unique:parents,phone,NULL,id,school_id,' . $request->user()->school_id,
            'email' => 'nullable|email|unique:users,email',
        ]);

        $user = \App\Models\User::create([
            'id' => (string) Str::uuid(),
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'password' => bcrypt($request->first_name),
            'school_id' => $request->user()->school_id,
            'role' => 'parent',
            'phone' => $request->phone,
            'address' => $request->address,
            'occupation' => $request->occupation,
            'nationality' => $request->nationality,
            'state_of_origin' => $request->state_of_origin,
            'local_government_area' => $request->local_government_area,
        ]);

        $parentRole = Role::query()->updateOrCreate(
            [
                'name' => 'parent',
                'school_id' => $request->user()->school_id,
            ],
            [
                'guard_name' => config('permission.default_guard', 'sanctum'),
                'description' => 'Parent or guardian',
            ]
        );

        $this->withTeamContext($request->user()->school_id, function () use ($user, $parentRole) {
            if (! $user->hasRole($parentRole)) {
                $user->assignRole($parentRole);
            }
        });

        $parent = $request->user()->school->parents()->create(array_merge(
            $request->all(),
            [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
            ]
        ));

        $parent->loadMissing('user')->loadCount('students');

        return response()->json($parent, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\SchoolParent  $parent
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Get(
     *      path="/v1/parents/{id}",
     *      operationId="getParentById",
     *      tags={"school-v1.3"},
     *      summary="Get parent information",
     *      description="Returns parent data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Parent id",
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
    public function show(Request $request, SchoolParent $parent)
    {
        if ($parent->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $parent->loadMissing('user')->loadCount('students');

        return response()->json($parent);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\SchoolParent  $parent
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Put(
     *      path="/v1/parents/{id}",
     *      operationId="updateParent",
     *      tags={"school-v1.3"},
     *      summary="Update existing parent",
     *      description="Returns updated parent data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Parent id",
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
     *              @OA\Property(property="first_name", type="string", example="John"),
     *              @OA\Property(property="last_name", type="string", example="Doe"),
     *              @OA\Property(property="phone", type="string", example="1234567890"),
     *              @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *              @OA\Property(property="address", type="string", example="123 Main St"),
     *              @OA\Property(property="occupation", type="string", example="Engineer"),
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
    public function update(Request $request, SchoolParent $parent)
    {
        $this->ensurePermission($request, ['parents.update', 'parents.manage']);
        if ($parent->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|unique:parents,phone,' . $parent->id . ',id,school_id,' . $request->user()->school_id,
            'email' => 'nullable|email|unique:users,email,' . $parent->user_id,
        ]);

        $parent->user->update([
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'occupation' => $request->occupation,
            'nationality' => $request->nationality,
            'state_of_origin' => $request->state_of_origin,
            'local_government_area' => $request->local_government_area,
        ]);

        $parent->update($request->all());

        return response()->json(
            $parent->fresh()->loadMissing('user')->loadCount('students')
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\SchoolParent  $parent
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Delete(
     *      path="/v1/parents/{id}",
     *      operationId="deleteParent",
     *      tags={"school-v1.3"},
     *      summary="Delete existing parent",
     *      description="Deletes a record and returns no content",
     *      @OA\Parameter(
     *          name="id",
     *          description="Parent id",
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
    public function destroy(Request $request, SchoolParent $parent)
    {
        $this->ensurePermission($request, ['parents.delete', 'parents.manage']);
        if ($parent->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        if ($parent->students()->exists()) {
            return response()->json(['message' => 'Cannot delete parent with linked students.'], 409);
        }

        $parent->delete();
        $parent->user()->delete();

        return response()->json(null, 204);
    }

    /**
     * Execute callbacks with the Spatie permission team context scoped to the given school.
     *
     * @template TReturn
     *
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    private function withTeamContext(string $schoolId, callable $callback)
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = method_exists($registrar, 'getPermissionsTeamId')
            ? $registrar->getPermissionsTeamId()
            : null;

        $registrar->setPermissionsTeamId($schoolId);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previousTeam);
        }
    }
}
