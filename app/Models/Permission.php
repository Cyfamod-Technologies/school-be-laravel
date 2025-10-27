<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'school_id',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $permission): void {
            if (! $permission->guard_name) {
                $permission->guard_name = config('permission.default_guard', 'sanctum');
            }
        });
    }

    public function scopeForSchool(Builder $query, ?string $schoolId): Builder
    {
        if ($schoolId === null) {
            return $query->whereNull('school_id')
                ->where('guard_name', config('permission.default_guard', 'sanctum'));
        }

        return $query->where('school_id', $schoolId)
            ->where('guard_name', config('permission.default_guard', 'sanctum'));
    }
}
