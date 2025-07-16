<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\School;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="School API",
 *      description="API for managing school data"
 * )
 */
class SchoolController extends Controller
{
    /**
     * @OA\Post(
     *      path="/v1/register-school",
     *      summary="Register a new school",
     *      tags={"school-v1.0"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name","address","email","password","password_confirmation"},
     *              @OA\Property(property="name", type="string", example="My School"),
     *              @OA\Property(property="address", type="string", example="123 Main St"),
     *              @OA\Property(property="email", type="string", format="email", example="school@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="password"),
     *              @OA\Property(property="password_confirmation", type="string", format="password", example="password"),
     *              @OA\Property(property="subdomain", type="string", example="my-school")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="School registered successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="School registered successfully"),
     *              @OA\Property(property="school", type="object",
     *                  @OA\Property(property="id", type="string", format="uuid"),
     *                  @OA\Property(property="name", type="string"),
     *                  @OA\Property(property="slug", type="string"),
     *                  @OA\Property(property="address", type="string"),
     *                  @OA\Property(property="email", type="string", format="email"),
     *                  @OA\Property(property="phone", type="string"),
     *                  @OA\Property(property="logo_url", type="string"),
     *                  @OA\Property(property="established_at", type="string", format="date"),
     *                  @OA\Property(property="owner_name", type="string"),
     *                  @OA\Property(property="status", type="string", enum={"active", "inactive"}),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time")
     *              ),
     *              @OA\Property(property="user", type="object",
     *                  @OA\Property(property="id", type="string", format="uuid"),
     *                  @OA\Property(property="name", type="string"),
     *                  @OA\Property(property="email", type="string", format="email"),
     *                  @OA\Property(property="role", type="string", enum={"staff", "parent", "super_admin", "accountant"}),
     *                  @OA\Property(property="status", type="string", enum={"active", "inactive", "suspended"}),
     *                  @OA\Property(property="last_login", type="string", format="date-time"),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error"
     *      )
     * )
     */
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'email' => 'required|string|email|max:255|unique:schools',
            'password' => 'required|string|min:8|confirmed',
            'subdomain' => 'required|string|max:255|unique:schools',
        ]);

        $school = School::create([
            'id' => Str::uuid(),
            'name' => $validatedData['name'],
            'slug' => Str::slug($validatedData['name']),
            'subdomain' => $validatedData['subdomain'],
            'address' => $validatedData['address'],
            'email' => $validatedData['email'],
            'phone' => '1234567890', // Add a dummy phone number
        ]);

        $user = User::create([
            'id' => Str::uuid(),
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'role' => 'admin',
            'school_id' => $school->id,
            'status' => 'active',
        ]);

        $loginUrl = str_replace('://', '://' . $school->subdomain . '.', config('app.url'));

        return response()->json([
            'message' => 'School registered successfully. Login via ' . $loginUrl,
            'school' => $school,
            'user' => $user,
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/v1/login",
     *     summary="Login as a school admin",
     *     tags={"school-v1.0"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful login",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="user", type="object"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are not correct.'],
            ]);
        }

        if ($user->role !== 'staff' && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/v1/logout",
     *     summary="Logout the authenticated user",
     *     tags={"school-v1.0"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
    /**
     * @OA\Put(
     *     path="/v1/school",
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
     *     path="/v1/user",
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
