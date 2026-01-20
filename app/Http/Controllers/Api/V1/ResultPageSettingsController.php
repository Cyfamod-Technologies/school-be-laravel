<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;

class ResultPageSettingsController extends Controller
{
    public function show(Request $request)
    {
        $school = optional($request->user())->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        return response()->json([
            'data' => $this->resolveSettings($school),
        ]);
    }

    public function update(Request $request)
    {
        $school = optional($request->user())->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'show_grade' => ['sometimes', 'boolean'],
            'show_position' => ['sometimes', 'boolean'],
            'show_class_average' => ['sometimes', 'boolean'],
            'show_lowest' => ['sometimes', 'boolean'],
            'show_highest' => ['sometimes', 'boolean'],
            'show_remarks' => ['sometimes', 'boolean'],
        ]);

        $current = $this->resolveSettings($school);
        $next = array_merge($current, $validated);

        $school->fill([
            'result_show_grade' => $next['show_grade'],
            'result_show_position' => $next['show_position'],
            'result_show_class_average' => $next['show_class_average'],
            'result_show_lowest' => $next['show_lowest'],
            'result_show_highest' => $next['show_highest'],
            'result_show_remarks' => $next['show_remarks'],
        ]);

        if ($school->isDirty()) {
            $school->save();
        }

        return response()->json([
            'message' => 'Result page settings updated successfully.',
            'data' => $this->resolveSettings($school->fresh()),
        ]);
    }

    private function resolveSettings(School $school): array
    {
        return [
            'show_grade' => $school->result_show_grade ?? true,
            'show_position' => $school->result_show_position ?? true,
            'show_class_average' => $school->result_show_class_average ?? true,
            'show_lowest' => $school->result_show_lowest ?? true,
            'show_highest' => $school->result_show_highest ?? true,
            'show_remarks' => $school->result_show_remarks ?? true,
        ];
    }
}
