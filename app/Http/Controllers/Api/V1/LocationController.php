<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BloodGroup;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function countries()
    {
        $countries = Country::orderBy('name')->get(['id', 'name', 'code']);

        return response()->json([
            'data' => $countries,
        ]);
    }

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

    public function lgas(State $state)
    {
        $lgas = $state->local_government_areas()
            ->orderBy('name')
            ->get(['id', 'state_id', 'name']);

        return response()->json([
            'data' => $lgas,
        ]);
    }

    public function bloodGroups()
    {
        $groups = BloodGroup::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'data' => $groups,
        ]);
    }
}

