<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str; // at the top if you want to auto-generate slug
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="school-v1.1",
 *     description="Academic Session & Term Management"
 * )
 * @OA\Tag(
 *     name="school-v1.7",
 *     description="v1.7 – Subject & Teacher Assignments (supporting lookups)"
 * )
 * @OA\Tag(
 *     name="school-v1.8",
 *     description="v1.8 – Class Teacher Assignments (supporting lookups)"
 * )
 * @OA\Tag(
 *     name="school-v1.9",
 *     description="v1.9 – Results, Components, Grading & Skills (supporting lookups)"
 * )
 * @OA\Tag(
 *     name="school-v2.0",
 *     description="v2.0 – Rollover, Promotions, Attendance, Fees, Roles (supporting lookups)"
 * )
 */
class AcademicSessionController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/v1/sessions",
     *      operationId="getSessionsList",
     *      tags={"school-v1.1","school-v1.7","school-v1.8","school-v1.9","school-v2.0"},
     *      summary="Get list of sessions",
     *      description="Returns list of sessions",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       )
     *     )
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        // Allow all authenticated users of the school (including teachers)
        // to view the list of sessions; only restrict create/update/delete
        // to users with the sessions.manage permission.
        $schoolId = $user->school_id;
        $sessions = Session::where('school_id', $schoolId)->get();

        return response()->json($sessions);
    }

    /**
     * @OA\Post(
     *      path="/api/v1/sessions",
     *      operationId="storeSession",
     *      tags={"school-v1.1"},
     *      summary="Store new session",
     *      description="Returns session data",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="name", type="string", example="2025/2026"),
     *              @OA\Property(property="start_date", type="string", format="date", example="2025-09-01"),
     *              @OA\Property(property="end_date", type="string", format="date", example="2026-07-31")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation"
     *       ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request"
     *      )
     * )
     */
    public function store(Request $request)
    {
        $this->ensurePermission($request, 'sessions.create');
        $schoolId = $request->user()->school_id;
    
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                Rule::unique('sessions')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'slug' => 'string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
    
        $validated = $validator->validated();
        $validated['id'] = Str::uuid();
        $validated['school_id'] = $schoolId;
        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['status'] = 'active';
    
        $existingSession = Session::where('school_id', $schoolId)
            ->where(function ($query) use ($validated) {
                $query->where('start_date', '<=', $validated['end_date'])
                      ->where('end_date', '>=', $validated['start_date']);
            })->exists();
    
        if ($existingSession) {
            return response()->json(['error' => 'A session already exists within the specified date range.'], 400);
        }
    
        $session = Session::create($validated);
        return response()->json(['message' => 'Session created successfully', 'data' => $session], 201);
    }
    
/**
 * @OA\Get(
 *     path="/api/v1/sessions/{id}",
 *     operationId="getSessionById",
 *     tags={"school-v1.1"},
 *     summary="Get session information",
 *     description="Returns session data",
 *     @OA\Parameter(
 *         name="id",
 *         description="Session id",
 *         required=true,
 *         in="path",
 *         @OA\Schema(
 *             type="string",
 *             example=123
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation"
 *     )
 * )
 */

    public function show(Request $request, Session $session)
    {
        $this->ensurePermission($request, 'sessions.view');
        
        // Verify session belongs to user's school
        if ($session->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        
        return response()->json($session);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/sessions/{id}",
     *     operationId="updateSession",
     *     tags={"school-v1.1"},
     *     summary="Update existing session",
     *     description="Returns updated session data",
     *     @OA\Parameter(
     *         name="id",
     *         description="Session id",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "start_date", "end_date"},
     *             @OA\Property(property="name", type="string", example="2025/2026"),
     *             @OA\Property(property="slug", type="string", example="2025/2026"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-09-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2026-07-31")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     )
     * )
     */

     public function update(Request $request, Session $session)
     {
         $this->ensurePermission($request, 'sessions.update');
         
         $user = $request->user();
         $schoolId = $user->school_id;
         
         // Verify session belongs to user's school
         if ($session->school_id !== $schoolId) {
             return response()->json(['message' => 'Not Found'], 404);
         }
     
         $validator = Validator::make($request->all(), [
             'name' => [
                 'required',
                 Rule::unique('sessions')
                     ->ignore($session->id)
                     ->where(fn ($q) => $q->where('school_id', $schoolId)),
             ],
             'slug' => 'string',
             'start_date' => 'required|date',
             'end_date' => 'required|date|after:start_date',
         ]);
     
         if ($validator->fails()) {
             return response()->json(['error' => $validator->errors()], 400);
         }
     
         $validated = $validator->validated();
     
         // Optionally auto-generate slug if not provided
         if (empty($validated['slug'])) {
             $validated['slug'] = Str::slug($validated['name']);
         }
     
         $existingSession = Session::where('school_id', $schoolId)
             ->where('id', '!=', $session->id)
             ->where(function ($query) use ($validated) {
                 $query->where('start_date', '<=', $validated['end_date'])
                       ->where('end_date', '>=', $validated['start_date']);
             })->exists();
     
         if ($existingSession) {
             return response()->json(['error' => 'A session already exists within the specified date range.'], 400);
         }
     
         $session->update($validated);
         return response()->json(['message' => 'Session updated successfully', 'data' => $session]);
     }

    /**
     * @OA\Delete(
     *      path="/api/v1/sessions/{id}",
     *      operationId="deleteSession",
     *      tags={"school-v1.1"},
     *      summary="Delete existing session",
     *      description="Deletes a record and returns no content",
     *      @OA\Parameter(
     *          name="id",
     *          description="Session id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request"
     *      )
     * )
     */
    public function destroy(Request $request, Session $session)
    {
        $this->ensurePermission($request, 'sessions.delete');
        
        // Verify session belongs to user's school
        if ($session->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        
        if ($session->terms()->exists()) {
            return response()->json(['error' => 'Cannot delete session with linked terms.'], 400);
        }

        $session->delete();
        return response()->json(['message' => 'Session deleted successfully']);
    }

    /**
     * @OA\Get(
     *      path="/api/v1/sessions/{id}/terms",
     *      operationId="getTermsForSession",
     *      tags={"school-v1.1","school-v1.7","school-v1.8","school-v1.9","school-v2.0"},
     *      summary="Get list of terms for a session",
     *      description="Returns list of terms for a session",
     *      @OA\Parameter(
     *          name="id",
     *          description="Session id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       )
     *     )
     */
    public function getTermsForSession(Session $session)
    {
        return response()->json($session->terms);
    }

    /**
     * Get all terms for the authenticated user's school
     */
    public function getAllTerms(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $schoolId = $user->school_id;
        $terms = Term::whereHas('session', function ($query) use ($schoolId) {
            $query->where('school_id', $schoolId);
        })->get();

        return response()->json($terms);
    }

    /**
     * @OA\Post(
     *      path="/api/v1/sessions/{id}/terms",
     *      operationId="storeTerm",
     *      tags={"school-v1.1"},
     *      summary="Store new term",
     *      description="Returns term data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Session id",
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
     *              @OA\Property(property="name", type="string", example="1st"),
     *              @OA\Property(property="start_date", type="string", format="date", example="2025-09-01"),
     *              @OA\Property(property="end_date", type="string", format="date", example="2026-07-31")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation"
     *       ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request"
     *      )
     * )
     */
    public function storeTerm(Request $request, Session $session)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            // You can add more rules as needed
        ]);
    
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
    
        $validated = $validator->validated();
        $validated['id'] = \Illuminate\Support\Str::uuid();
        $validated['session_id'] = $session->id;
        $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        $validated['school_id'] = auth()->user()->school_id; // or $session->school_id;
        $validated['status'] = 'active';

        // Optionally, add any other logic for term uniqueness
        $existingTerm = $session->terms()
            ->where(function ($query) use ($validated) {
                $query->where('start_date', '<=', $validated['end_date'])
                      ->where('end_date', '>=', $validated['start_date']);
            })->exists();
    
        if ($existingTerm) {
            return response()->json(['error' => 'A term already exists within the specified date range for this session.'], 400);
        }
    
        $term = $session->terms()->create($validated);
        return response()->json(['message' => 'Term created successfully', 'data' => $term], 201);
    }

    /**
     * @OA\Get(
     *      path="/api/v1/terms/{id}",
     *      operationId="getTermById",
     *      tags={"school-v1.1","school-v1.7","school-v1.8","school-v1.9","school-v2.0"},
     *      summary="Get term information",
     *      description="Returns term data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Term id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       ),
     *      @OA\Response(
     *          response=404,
     *          description="Resource Not Found"
     *      )
     * )
     */
    public function showTerm(Request $request, Term $term)
    {
        if ($term->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json(
            $term->loadMissing('session:id,name')
        );
    }


    /**
     * @OA\Put(
     *      path="/api/v1/terms/{id}",
     *      operationId="updateTerm",
     *      tags={"school-v1.1"},
     *      summary="Update existing term",
     *      description="Returns updated term data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Term id",
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
     *              @OA\Property(property="name", type="string", example="2nd"),
     *              @OA\Property(property="slug", type="string", example="2nd"),
     *              @OA\Property(property="start_date", type="string", format="date", example="2025-09-01"),
     *              @OA\Property(property="end_date", type="string", format="date", example="2026-07-31")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request"
     *      )
     * )
     */
    public function updateTerm(Request $request, Term $term)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'slug' => 'string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $validated = $validator->validated();

        $existingTerm = $term->session->terms()->where('id', '!=', $term->id)
            ->where(function ($query) use ($validated) {
                $query->where('start_date', '<=', $validated['end_date'])
                      ->where('end_date', '>=', $validated['start_date']);
            })->exists();

        if ($existingTerm) {
            return response()->json(['error' => 'A term already exists within the specified date range for this session.'], 400);
        }

        $term->update($validated);
        return response()->json(['message' => 'Term updated successfully', 'data' => $term]);
    }

    /**
     * @OA\Delete(
     *      path="/api/v1/terms/{id}",
     *      operationId="deleteTerm",
     *      tags={"school-v1.1"},
     *      summary="Delete existing term",
     *      description="Deletes a record and returns no content",
     *      @OA\Parameter(
     *          name="id",
     *          description="Term id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request"
     *      )
     * )
     */
    public function destroyTerm(Term $term)
    {
        // Add logic to check for linked students or results
        // if ($term->students()->exists() || $term->results()->exists()) {
        //     return response()->json(['error' => 'Cannot delete term with linked students or results.'], 400);
        // }

        $term->delete();
        return response()->json(['message' => 'Term deleted successfully']);
    }
}
