<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="School API",
 *      description="API for managing school data"
 * )
 */
use App\Http\Controllers\Controller;

class SchoolRegistrationController extends Controller
{
    /**
     * @OA\Post(
     *      path="/register-school",
     *      summary="Register a new school",
     *      tags={"School"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name","address","email","password","password_confirmation"},
     *              @OA\Property(property="name", type="string", example="My School"),
     *              @OA\Property(property="address", type="string", example="123 Main St"),
     *              @OA\Property(property="email", type="string", format="email", example="school@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="password"),
     *              @OA\Property(property="password_confirmation", type="string", format="password", example="password")
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
            'name' => $validatedData['name'],
            'slug' => Str::slug($validatedData['name']),
            'subdomain' => $validatedData['subdomain'],
            'address' => $validatedData['address'],
            'email' => $validatedData['email'],
        ]);

        $user = User::create([
            'name' => $validatedData['name'] . ' Admin',
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'role' => 'super_admin',
            'school_id' => $school->id,
        ]);

        $loginUrl = str_replace('://', '://' . $school->subdomain . '.', config('app.url'));

        return response()->json([
            'message' => 'School registered successfully. Login via ' . $loginUrl,
            'school' => $school,
            'user' => $user,
        ], 201);
    }
}
