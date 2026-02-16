<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = optional($request->user())->school_id;

        if (empty($schoolId)) {
            throw ValidationException::withMessages([
                'school_id' => ['Authenticated user is not associated with a school.'],
            ]);
        }

        $perPage = max((int) $request->input('per_page', 25), 1);
        $searchTerm = trim((string) $request->input('search', ''));
        $supportOnly = $request->boolean('support_only', false);

        $users = $this->withTeamContext($schoolId, function () use ($schoolId, $perPage, $searchTerm, $supportOnly) {
            $query = User::query()
                ->where('school_id', $schoolId)
                ->orderBy('name')
                ->with([
                    'roles' => function ($relation) use ($schoolId) {
                        $relation
                            ->where('roles.school_id', $schoolId)
                            ->where('roles.guard_name', config('permission.default_guard', 'sanctum'));
                    },
                ]);

            if ($supportOnly) {
                $this->applySupportOnlyFilter($query, $schoolId);
            }

            if ($searchTerm !== '') {
                $query->where(function ($builder) use ($searchTerm) {
                    $builder
                        ->where('name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('email', 'like', '%' . $searchTerm . '%');
                });
            }

            return $query->paginate($perPage)->withQueryString();
        });

        return response()->json($users);
    }

    private function applySupportOnlyFilter(Builder $query, string $schoolId): void
    {
        $guard = config('permission.default_guard', 'sanctum');

        $query->where(function (Builder $builder) use ($schoolId, $guard) {
            $builder
                ->whereRaw('LOWER(COALESCE(role, "")) = ?', ['support'])
                ->orWhereHas('roles', function (Builder $roleQuery) use ($schoolId, $guard) {
                    $roleQuery
                        ->where('roles.school_id', $schoolId)
                        ->where('roles.guard_name', $guard)
                        ->whereRaw('LOWER(roles.name) = ?', ['support']);
                })
                ->orWhereHas('staff', function (Builder $staffQuery) use ($schoolId) {
                    $staffQuery
                        ->where('school_id', $schoolId)
                        ->whereRaw('LOWER(COALESCE(role, "")) = ?', ['support']);
                });
        });
    }

    /**
     * @template TReturn
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    private function withTeamContext(string $schoolId, callable $callback)
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = method_exists($registrar, 'getPermissionsTeamId')
            ? $registrar->getPermissionsTeamId()
            : null;

        $registrar->setPermissionsTeamId($schoolId);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previousTeam);
        }
    }
}
