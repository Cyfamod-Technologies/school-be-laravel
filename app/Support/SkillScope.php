<?php

namespace App\Support;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class SkillScope
{
    public static function categorySeparatedByClass(?School $school): bool
    {
        return (bool) ($school?->skill_categories_separate_by_class ?? false);
    }

    public static function typeSeparatedByClass(?School $school): bool
    {
        return (bool) ($school?->skill_types_separate_by_class ?? false);
    }

    public static function normalizeClassId(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    public static function applyCategoryVisibility(
        Builder|Relation $query,
        School $school,
        ?string $classId = null
    ): Builder|Relation {
        $query->where('school_id', $school->id);

        if (! self::categorySeparatedByClass($school) || ! $classId) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($classId) {
            $builder
                ->whereNull('school_class_id')
                ->orWhere('school_class_id', $classId);
        });
    }

    public static function applyTypeVisibility(
        Builder|Relation $query,
        School $school,
        ?string $classId = null
    ): Builder|Relation {
        $query->where('school_id', $school->id);

        if (self::typeSeparatedByClass($school) && $classId) {
            $query->where(function (Builder $builder) use ($classId) {
                $builder
                    ->whereNull('school_class_id')
                    ->orWhere('school_class_id', $classId);
            });
        }

        if (self::categorySeparatedByClass($school) && $classId) {
            $query->whereHas('skill_category', function (Builder $builder) use ($classId) {
                $builder->where(function (Builder $categoryBuilder) use ($classId) {
                    $categoryBuilder
                        ->whereNull('school_class_id')
                        ->orWhere('school_class_id', $classId);
                });
            });
        }

        return $query;
    }

    public static function isCategoryVisibleForClass(
        School $school,
        ?string $categoryClassId,
        ?string $classId
    ): bool {
        if (! self::categorySeparatedByClass($school) || ! $classId) {
            return true;
        }

        return $categoryClassId === null || $categoryClassId === $classId;
    }

    public static function isTypeVisibleForClass(
        School $school,
        ?string $typeClassId,
        ?string $classId
    ): bool {
        if (! self::typeSeparatedByClass($school) || ! $classId) {
            return true;
        }

        return $typeClassId === null || $typeClassId === $classId;
    }
}
