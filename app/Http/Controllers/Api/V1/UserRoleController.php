<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    /**
     * @OA\Post(
     *      path="/v1/users/{user}/assign-role",
     *      operationId="assignRole",
     *      tags={"Users"},
     *      summary="Assign role to user",
     *      description="Assigns a role to a user",
     *      @OA\Parameter(
     *          name="user",
     *          description="User id",
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
     *              @OA\Property(property="role_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
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
    public function assignRole(Request $request, User $user)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        if ($user->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $user->roles()->syncWithoutDetaching($request->role_id);

        return response()->json(['message' => 'Role assigned successfully.']);
    }

    /**
     * @OA\Post(
     *      path="/v1/users/{user}/unassign-role",
     *      operationId="unassignRole",
     *      tags={"Users"},
     *      summary="Unassign role from user",
     *      description="Unassigns a role from a user",
     *      @OA\Parameter(
     *          name="user",
     *          description="User id",
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
     *              @OA\Property(property="role_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
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
    public function unassignRole(Request $request, User $user)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        if ($user->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $user->roles()->detach($request->role_id);

        return response()->json(['message' => 'Role unassigned successfully.']);
    }
}
