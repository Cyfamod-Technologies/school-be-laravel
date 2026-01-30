<?php

namespace Database\Seeders;

use App\Models\GradeRange;
use App\Models\GradingScale;
use App\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DefaultGradeScaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        School::query()->chunkById(50, function (Collection $schools) {
            foreach ($schools as $school) {
                $scale = GradingScale::firstOrCreate(
                    [
                        'school_id' => $school->id,
                        'name' => 'Default',
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'description' => 'Default grading scale',
                    ]
                );

                $this->syncDefaultRanges($scale);
            }
        });
    }

    private function syncDefaultRanges(GradingScale $scale): void
    {
        $defaults = collect(self::defaultRanges());

        $existingLabels = $scale->grade_ranges()->pluck('grade_label');
        if ($existingLabels->isNotEmpty()) {
            return;
        }

        $defaults->each(function (array $range) use ($scale, $existingLabels) {
            $payload = [
                'grading_scale_id' => $scale->id,
                'min_score' => $range['min'],
                'max_score' => $range['max'],
                'description' => Arr::get($range, 'description'),
                'grade_point' => Arr::get($range, 'point'),
            ];

            if ($existingLabels->contains($range['label'])) {
                $gradeRange = $scale->grade_ranges()->where('grade_label', $range['label'])->first();
                if ($gradeRange) {
                    $gradeRange->fill($payload);
                    if ($gradeRange->isDirty()) {
                        $gradeRange->save();
                    }
                }
            } else {
                GradeRange::create(
                    [
                        'id' => (string) Str::uuid(),
                        'grade_label' => $range['label'],
                    ] + $payload
                );
            }
        });
    }

    public static function defaultRanges(): array
    {
        return [
            ['label' => 'A1', 'min' => 75, 'max' => 100, 'description' => 'Distinction'],
            ['label' => 'B2', 'min' => 70, 'max' => 74, 'description' => 'Very Good'],
            ['label' => 'B3', 'min' => 65, 'max' => 69, 'description' => 'Good'],
            ['label' => 'C4', 'min' => 60, 'max' => 64, 'description' => 'Credit'],
            ['label' => 'C5', 'min' => 55, 'max' => 59, 'description' => 'Credit'],
            ['label' => 'C6', 'min' => 50, 'max' => 54, 'description' => 'Credit'],
            ['label' => 'D7', 'min' => 45, 'max' => 49, 'description' => 'Pass'],
            ['label' => 'E8', 'min' => 40, 'max' => 44, 'description' => 'Pass'],
            ['label' => 'F9', 'min' => 0,  'max' => 39,  'description' => 'Fail'],
        ];
    }
}
