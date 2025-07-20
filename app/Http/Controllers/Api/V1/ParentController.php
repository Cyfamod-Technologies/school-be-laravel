<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SchoolParent;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Get(
     *      path="/parents",
     *      operationId="getParentsList",
     *      tags={"Parents"},
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
        $parents = SchoolParent::withCount('students')
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('first_name', 'like', '%' . $request->search . '%')
                    ->orWhere('last_name', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%');
            })
            ->paginate(10);

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
     *      path="/parents",
     *      operationId="storeParent",
     *      tags={"Parents"},
     *      summary="Store new parent",
     *      description="Returns parent data",
     *      @OA\RequestBody(
     *          required=true,
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
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|unique:parents,phone,NULL,id,school_id,' . $request->user()->school_id,
            'email' => 'nullable|email|unique:parents,email,NULL,id,school_id,' . $request->user()->school_id,
        ]);

        $parent = SchoolParent::create($request->all());

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
     *      path="/parents/{id}",
     *      operationId="getParentById",
     *      tags={"Parents"},
     *      summary="Get parent information",
     *      description="Returns parent data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Parent id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
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
    public function show(SchoolParent $parent)
    {
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
     *      path="/parents/{id}",
     *      operationId="updateParent",
     *      tags={"Parents"},
     *      summary="Update existing parent",
     *      description="Returns updated parent data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Parent id",
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
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|unique:parents,phone,' . $parent->id . ',id,school_id,' . $request->user()->school_id,
            'email' => 'nullable|email|unique:parents,email,' . $parent->id . ',id,school_id,' . $request->user()->school_id,
        ]);

        $parent->update($request->all());

        return response()->json($parent);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\SchoolParent  $parent
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Delete(
     *      path="/parents/{id}",
     *      operationId="deleteParent",
     *      tags={"Parents"},
     *      summary="Delete existing parent",
     *      description="Deletes a record and returns no content",
     *      @OA\Parameter(
     *          name="id",
     *          description="Parent id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
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
    public function destroy(SchoolParent $parent)
    {
        if ($parent->students()->exists()) {
            return response()->json(['message' => 'Cannot delete parent with linked students.'], 409);
        }

        $parent->delete();

        return response()->json(null, 204);
    }
}
