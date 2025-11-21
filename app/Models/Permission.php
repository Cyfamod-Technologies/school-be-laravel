<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

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

            $teamId = self::resolveTeamId();

            if ($teamId !== null && ! array_key_exists('school_id', $permission->getAttributes())) {
                $permission->school_id = $teamId;
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

    public static function findByName(string $name, ?string $guardName = null): self
    {
        $guardName ??= Guard::getDefaultName(static::class);
        $teamId = self::resolveTeamId();

        $permission = self::query()
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->when($teamId !== null, fn ($query) => $query->where('school_id', $teamId))
            ->first();

        if (! $permission && $teamId !== null) {
            $permission = self::query()
                ->where('name', $name)
                ->where('guard_name', $guardName)
                ->whereNull('school_id')
                ->first();
        }

        if (! $permission) {
            $permission = self::query()
                ->where('name', $name)
                ->where('guard_name', $guardName)
                ->first();
        }

        if (! $permission) {
            throw PermissionDoesNotExist::create($name, $guardName);
        }

        return $permission;
    }

    public static function findById($id, ?string $guardName = null): self
    {
        $guardName ??= Guard::getDefaultName(static::class);
        $teamId = self::resolveTeamId();

        $permission = self::query()
            ->where((new self())->getKeyName(), $id)
            ->where('guard_name', $guardName)
            ->when($teamId !== null, fn ($query) => $query->where('school_id', $teamId))
            ->first();

        if (! $permission && $teamId !== null) {
            $permission = self::query()
                ->where((new self())->getKeyName(), $id)
                ->where('guard_name', $guardName)
                ->whereNull('school_id')
                ->first();
        }

        if (! $permission) {
            throw PermissionDoesNotExist::withId($id, $guardName);
        }

        return $permission;
    }

    public static function findOrCreate(string $name, ?string $guardName = null): self
    {
        $guardName ??= Guard::getDefaultName(static::class);
        $teamId = self::resolveTeamId();

        $existing = self::query()
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->when($teamId !== null, fn ($query) => $query->where('school_id', $teamId))
            ->first();

        if ($existing) {
            return $existing;
        }

        return tap(
            self::query()->create([
                'name' => $name,
                'guard_name' => $guardName,
                'school_id' => $teamId,
            ])
        )->fresh();
    }

    private static function resolveTeamId(): ?string
    {
        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->teams) {
            return null;
        }

        return getPermissionsTeamId();
    }
}
