<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        $users = $this->withTeamContext($schoolId, function () use ($schoolId, $perPage, $searchTerm) {
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
