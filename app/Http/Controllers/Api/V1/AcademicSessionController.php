<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="school-v1.1",
 *     description="API Endpoints for School v1.1"
 * )
 */
class AcademicSessionController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/v1/sessions",
     *      operationId="getSessionsList",
     *      tags={"school-v1.1"},
     *      summary="Get list of sessions",
     *      description="Returns list of sessions",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       )
     *     )
     */
    public function index()
    {
        return response()->json(Session::all());
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
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:sessions,name',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $validated = $validator->validated();

        $existingSession = Session::where(function ($query) use ($validated) {
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
     *      path="/api/v1/sessions/{id}",
     *      operationId="getSessionById",
     *      tags={"school-v1.1"},
     *      summary="Get session information",
     *      description="Returns session data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Session id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *       )
     * )
     */
    public function show(Session $session)
    {
        return response()->json($session);
    }

    /**
     * @OA\Put(
     *      path="/api/v1/sessions/{id}",
     *      operationId="updateSession",
     *      tags={"school-v1.1"},
     *      summary="Update existing session",
     *      description="Returns updated session data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Session id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
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
    public function update(Request $request, Session $session)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:sessions,name,' . $session->id,
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $validated = $validator->validated();

        $existingSession = Session::where('id', '!=', $session->id)
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
     *              type="integer"
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
    public function destroy(Session $session)
    {
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
     *      tags={"school-v1.1"},
     *      summary="Get list of terms for a session",
     *      description="Returns list of terms for a session",
     *      @OA\Parameter(
     *          name="id",
     *          description="Session id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
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
     *              type="integer"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
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
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $validated = $validator->validated();

        $existingTerm = $session->terms()->where(function ($query) use ($validated) {
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
     *              type="integer"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
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
     *              type="integer"
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
