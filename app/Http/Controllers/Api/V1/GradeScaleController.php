<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GradeRange;
use App\Models\GradingScale;
use App\Models\PositionRange;
use Database\Seeders\DefaultGradeScaleSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="school-v1.9",
 *     description="v1.9 – Results, Components, Grading & Skills"
 * )
 */
class GradeScaleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/grades/scales",
     *     tags={"school-v1.9"},
     *     summary="List grading scales",
     *     @OA\Response(response=200, description="Scales returned"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $school = optional($request->user())->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $scales = GradingScale::query()
            ->where('school_id', $school->id)
            ->with([
                'grade_ranges' => function ($query) {
                    $query->orderByDesc('min_score');
                },
                'position_ranges' => function ($query) {
                    $query->orderBy('position');
                },
            ])
            ->orderBy('name')
            ->get();

        if ($scales->isEmpty()) {
            $scales = collect([$this->createDefaultScale($school->id)]);
        }

        return response()->json([
            'data' => $scales,
        ]);
    }

    public function show(Request $request, GradingScale $gradingScale)
    {
        $this->authorizeScale($request, $gradingScale);

        return response()->json(
            $gradingScale->load([
                'grade_ranges' => function ($query) {
                    $query->orderByDesc('min_score');
                },
                'position_ranges' => function ($query) {
                    $query->orderBy('position');
                },
            ])
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/grades/scales/{id}",
     *     tags={"school-v1.9"},
     *     summary="Update grading scale ranges",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ranges"},
     *             @OA\Property(property="ranges", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="min_score", type="number"),
     *                 @OA\Property(property="max_score", type="number"),
     *                 @OA\Property(property="grade_label", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="grade_point", type="number")
     *             )),
     *             @OA\Property(property="deleted_ids", type="array", @OA\Items(type="string", format="uuid"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Scale updated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateRanges(Request $request, GradingScale $gradingScale)
    {
        $this->authorizeScale($request, $gradingScale);

        $validated = $request->validate([
            'ranges' => ['required', 'array', 'min:1'],
            'ranges.*.id' => ['nullable', 'uuid'],
            'ranges.*.min_score' => ['required', 'numeric', 'between:0,100'],
            'ranges.*.max_score' => ['required', 'numeric', 'between:0,100'],
            'ranges.*.grade_label' => ['required', 'string', 'max:50'],
            'ranges.*.description' => ['nullable', 'string', 'max:255'],
            'ranges.*.grade_point' => ['nullable', 'numeric', 'between:0,10'],
            'deleted_ids' => ['nullable', 'array'],
            'deleted_ids.*' => ['uuid'],
        ]);

        $normalized = $this->normalizeRanges($validated['ranges']);

        DB::transaction(function () use ($gradingScale, $normalized, $validated) {
            $existingRanges = $gradingScale->grade_ranges()->get()->keyBy('id');

            if (! empty($validated['deleted_ids'])) {
                $this->handleDeletions($gradingScale, $validated['deleted_ids'], $existingRanges);
            }

            foreach ($normalized as $range) {
                if (! empty($range['id'])) {
                    /** @var GradeRange|null $model */
                    $model = $existingRanges->get($range['id']);
                    if (! $model) {
                        throw ValidationException::withMessages([
                            'ranges' => ["Grade range {$range['id']} was not found."],
                        ]);
                    }

                    $model->fill(Arr::except($range, ['id']));
                    if ($model->isDirty()) {
                        $model->save();
                    }
                } else {
                    GradeRange::create([
                        'id' => (string) Str::uuid(),
                        'grading_scale_id' => $gradingScale->id,
                        'min_score' => $range['min_score'],
                        'max_score' => $range['max_score'],
                        'grade_label' => $range['grade_label'],
                        'description' => $range['description'],
                        'grade_point' => $range['grade_point'],
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Grading scale updated successfully.',
            'data' => $gradingScale->fresh([
                'grade_ranges' => function ($query) {
                    $query->orderByDesc('min_score');
                },
                'position_ranges' => function ($query) {
                    $query->orderBy('position');
                },
            ]),
        ]);
    }

    public function updatePositionRanges(Request $request, GradingScale $gradingScale)
    {
        $this->authorizeScale($request, $gradingScale);

        $validated = $request->validate([
            'ranges' => ['required', 'array'],
            'ranges.*.id' => ['nullable', 'uuid'],
            'ranges.*.min_score' => ['required', 'numeric', 'between:0,100'],
            'ranges.*.max_score' => ['required', 'numeric', 'between:0,100'],
            'ranges.*.position' => ['required', 'integer', 'min:1'],
            'deleted_ids' => ['nullable', 'array'],
            'deleted_ids.*' => ['uuid'],
        ]);

        $normalized = $this->normalizePositionRanges($validated['ranges']);

        DB::transaction(function () use ($gradingScale, $normalized, $validated) {
            $existingRanges = $gradingScale->position_ranges()->get()->keyBy('id');

            if (! empty($validated['deleted_ids'])) {
                foreach ($validated['deleted_ids'] as $id) {
                    /** @var PositionRange|null $range */
                    $range = $existingRanges->get($id);
                    if (! $range) {
                        throw ValidationException::withMessages([
                            'deleted_ids' => ["Position range {$id} was not found."],
                        ]);
                    }
                    $range->delete();
                }
            }

            foreach ($normalized as $range) {
                if (! empty($range['id'])) {
                    /** @var PositionRange|null $model */
                    $model = $existingRanges->get($range['id']);
                    if (! $model) {
                        throw ValidationException::withMessages([
                            'ranges' => ["Position range {$range['id']} was not found."],
                        ]);
                    }

                    $model->fill(Arr::except($range, ['id']));
                    if ($model->isDirty()) {
                        $model->save();
                    }
                } else {
                    PositionRange::create([
                        'id' => (string) Str::uuid(),
                        'grading_scale_id' => $gradingScale->id,
                        'min_score' => $range['min_score'],
                        'max_score' => $range['max_score'],
                        'position' => $range['position'],
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Position ranges updated successfully.',
            'data' => $gradingScale->fresh([
                'grade_ranges' => function ($query) {
                    $query->orderByDesc('min_score');
                },
                'position_ranges' => function ($query) {
                    $query->orderBy('position');
                },
            ]),
        ]);
    }

    public function destroyPositionRange(Request $request, PositionRange $positionRange)
    {
        $gradingScale = $positionRange->grading_scale;
        $this->authorizeScale($request, $gradingScale);

        $positionRange->delete();

        return response()->json([
            'message' => 'Position range deleted successfully.',
        ]);
    }

    public function destroyRange(Request $request, GradeRange $gradeRange)
    {
        $gradingScale = $gradeRange->grading_scale;
        $this->authorizeScale($request, $gradingScale);

        if ($gradeRange->results()->exists()) {
            return response()->json([
                'message' => 'Cannot delete grade range because results already reference it.',
            ], 422);
        }

        $gradeRange->delete();

        return response()->json([
            'message' => 'Grade range deleted successfully.',
        ]);
    }

    private function authorizeScale(Request $request, GradingScale $gradingScale): void
    {
        $school = optional($request->user())->school;

        abort_unless($school && $gradingScale->school_id === $school->id, 404);
    }

    private function createDefaultScale(string $schoolId): GradingScale
    {
        /** @var GradingScale $scale */
        $scale = GradingScale::create([
            'id' => (string) Str::uuid(),
            'school_id' => $schoolId,
            'name' => 'Default',
            'description' => 'Default grading scale',
        ]);

        app(DefaultGradeScaleSeeder::class)->run();

        return $scale->fresh([
            'grade_ranges' => function ($query) {
                $query->orderByDesc('min_score');
            },
            'position_ranges' => function ($query) {
                $query->orderBy('position');
            },
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $ranges
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRanges(array $ranges): array
    {
        $normalized = collect($ranges)->map(function (array $range) {
            $min = (float) $range['min_score'];
            $max = (float) $range['max_score'];

            if ($min > $max) {
                throw ValidationException::withMessages([
                    'ranges' => ['Minimum score cannot be greater than maximum score.'],
                ]);
            }

            return [
                'id' => Arr::get($range, 'id'),
                'min_score' => round($min, 2),
                'max_score' => round($max, 2),
                'grade_label' => strtoupper(trim($range['grade_label'])),
                'description' => Arr::get($range, 'description'),
                'grade_point' => Arr::get($range, 'grade_point'),
            ];
        });

        $labels = $normalized->pluck('grade_label');
        if ($labels->count() !== $labels->unique()->count()) {
            throw ValidationException::withMessages([
                'ranges' => ['Grade labels must be unique.'],
            ]);
        }

        $sorted = $normalized->sortBy('min_score')->values();
        for ($i = 1; $i < $sorted->count(); $i++) {
            $previous = $sorted[$i - 1];
            $current = $sorted[$i];

            if ($previous['max_score'] >= $current['min_score']) {
                throw ValidationException::withMessages([
                    'ranges' => ['Grade ranges must not overlap.'],
                ]);
            }
        }

        if ((int) round($sorted->first()['min_score']) !== 0 || (int) round($sorted->last()['max_score']) !== 100) {
            throw ValidationException::withMessages([
                'ranges' => ['Grade ranges must start at 0 and end at 100.'],
            ]);
        }

        for ($i = 1; $i < $sorted->count(); $i++) {
            $previous = $sorted[$i - 1];
            $current = $sorted[$i];

            if ($previous['max_score'] >= $current['min_score']) {
                throw ValidationException::withMessages([
                    'ranges' => ['Grade ranges must not overlap.'],
                ]);
            }

            $expectedNextMin = (int) round($previous['max_score']) + 1;
            if ((int) round($current['min_score']) !== $expectedNextMin) {
                throw ValidationException::withMessages([
                    'ranges' => ['Grade ranges must be contiguous without gaps.'],
                ]);
            }
        }

        return $normalized->all();
    }

    private function handleDeletions(GradingScale $gradingScale, array $deletedIds, Collection $existingRanges): void
    {
        foreach ($deletedIds as $id) {
            /** @var GradeRange|null $gradeRange */
            $gradeRange = $existingRanges->get($id);
            if (! $gradeRange) {
                throw ValidationException::withMessages([
                    'deleted_ids' => ["Grade range {$id} was not found."],
                ]);
            }

            if ($gradeRange->results()->exists()) {
                throw ValidationException::withMessages([
                    'deleted_ids' => ["Grade range {$gradeRange->grade_label} cannot be deleted because results reference it."],
                ]);
            }

            $gradeRange->delete();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $ranges
     * @return array<int, array<string, mixed>>
     */
    private function normalizePositionRanges(array $ranges): array
    {
        if (empty($ranges)) {
            return [];
        }

        $normalized = collect($ranges)->map(function (array $range) {
            $min = (float) $range['min_score'];
            $max = (float) $range['max_score'];

            if ($min > $max) {
                throw ValidationException::withMessages([
                    'ranges' => ['Minimum score cannot be greater than maximum score.'],
                ]);
            }

            return [
                'id' => Arr::get($range, 'id'),
                'min_score' => round($min, 2),
                'max_score' => round($max, 2),
                'position' => (int) $range['position'],
            ];
        });

        $positions = $normalized->pluck('position');
        if ($positions->count() !== $positions->unique()->count()) {
            throw ValidationException::withMessages([
                'ranges' => ['Position numbers must be unique.'],
            ]);
        }

        $sortedByScore = $normalized->sortBy('min_score')->values();
        for ($i = 1; $i < $sortedByScore->count(); $i++) {
            $previous = $sortedByScore[$i - 1];
            $current = $sortedByScore[$i];

            if ($previous['max_score'] >= $current['min_score']) {
                throw ValidationException::withMessages([
                    'ranges' => ['Position ranges must not overlap.'],
                ]);
            }
        }

        return $normalized->all();
    }
}
