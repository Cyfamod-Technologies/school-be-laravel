<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BloodGroup;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/locations/countries",
     *     tags={"school-v1.4"},
     *     summary="List countries",
     *     @OA\Response(response=200, description="Countries returned")
     * )
     */
    public function countries()
    {
        $countries = Country::orderBy('name')->get(['id', 'name', 'code']);

        return response()->json([
            'data' => $countries,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/locations/states",
     *     tags={"school-v1.4"},
     *     summary="List states",
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         required=false,
     *         description="Filter states by country",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="States returned")
     * )
     */
    public function states(Request $request)
    {
        $query = State::query()->orderBy('name');

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->input('country_id'));
        }

        $states = $query->get(['id', 'country_id', 'name', 'code']);

        return response()->json([
            'data' => $states,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/locations/states/{state}/lgas",
     *     tags={"school-v1.4"},
     *     summary="List LGAs for a state",
     *     @OA\Parameter(
     *         name="state",
     *         in="path",
     *         required=true,
     *         description="State ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="LGAs returned")
     * )
     */
    public function lgas(State $state)
    {
        $lgas = $state->local_government_areas()
            ->orderBy('name')
            ->get(['id', 'state_id', 'name']);

        return response()->json([
            'data' => $lgas,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/locations/blood-groups",
     *     tags={"school-v1.4"},
     *     summary="List blood groups",
     *     @OA\Response(response=200, description="Blood groups returned")
     * )
     */
    public function bloodGroups()
    {
        $groups = BloodGroup::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'data' => $groups,
        ]);
    }
}
