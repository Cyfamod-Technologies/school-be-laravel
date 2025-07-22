<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * @OA\Get(
     *      path="/v1/roles",
     *      operationId="getRolesList",
     *      tags={"Roles"},
     *      summary="Get list of roles",
     *      description="Returns list of roles",
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
        $roles = $request->user()->school->roles()->with('permissions')->paginate(10);
        return response()->json($roles);
    }

    /**
     * @OA\Post(
     *      path="/v1/roles",
     *      operationId="storeRole",
     *      tags={"Roles"},
     *      summary="Store new role",
     *      description="Returns role data",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="name", type="string", example="Admin"),
     *              @OA\Property(property="description", type="string", example="Administrator role"),
     *              @OA\Property(property="permissions", type="array", @OA\Items(type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx")),
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role = $request->user()->school->roles()->create($request->only('name', 'description'));

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json($role, 201);
    }

    /**
     * @OA\Get(
     *      path="/v1/roles/{id}",
     *      operationId="getRoleById",
     *      tags={"Roles"},
     *      summary="Get role information",
     *      description="Returns role data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Role id",
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
    public function show(Request $request, Role $role)
    {
        if ($role->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        $role->load('permissions');
        return response()->json($role);
    }

    /**
     * @OA\Put(
     *      path="/v1/roles/{id}",
     *      operationId="updateRole",
     *      tags={"Roles"},
     *      summary="Update existing role",
     *      description="Returns updated role data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Role id",
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
     *              @OA\Property(property="name", type="string", example="Admin"),
     *              @OA\Property(property="description", type="string", example="Administrator role"),
     *              @OA\Property(property="permissions", type="array", @OA\Items(type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx")),
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
    public function update(Request $request, Role $role)
    {
        if ($role->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role->update($request->only('name', 'description'));

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json($role);
    }

    /**
     * @OA\Delete(
     *      path="/v1/roles/{id}",
     *      operationId="deleteRole",
     *      tags={"Roles"},
     *      summary="Delete existing role",
     *      description="Deletes a record and returns no content",
     *      @OA\Parameter(
     *          name="id",
     *          description="Role id",
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
    public function destroy(Request $request, Role $role)
    {
        if ($role->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $role->delete();

        return response()->json(null, 204);
    }
}
