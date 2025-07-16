<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\School;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SchoolController extends Controller
{
    /**
     * @OA\Put(
     *     path="/api/v1/school",
     *     summary="Update school profile",
     *     tags={"school-v1.0"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="logo_url", type="string"),
     *                 @OA\Property(property="established_at", type="string", format="date"),
     *                 @OA\Property(property="owner_name", type="string"),
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="School profile updated successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateSchoolProfile(Request $request)
    {
        $user = Auth::user();
        $school = $user->school;

        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'address' => 'string',
            'email' => 'string|email|max:255',
            'phone' => 'string|max:50',
            'logo_url' => 'string|max:512',
            'established_at' => 'date',
            'owner_name' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $school->update($request->all());

        return response()->json(['message' => 'School profile updated successfully', 'school' => $school]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/user",
     *     summary="Update user profile",
     *     tags={"school-v1.0"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="User profile updated successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updatePersonalProfile(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users,email,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user->update($request->all());

        return response()->json(['message' => 'User profile updated successfully', 'user' => $user]);
    }
}
