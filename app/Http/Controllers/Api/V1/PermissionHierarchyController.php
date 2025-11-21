<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Rbac\RbacService;

class PermissionHierarchyController extends Controller
{
    public function index(RbacService $rbacService)
    {
        $permissions = $rbacService->getCorePermissionsWithChildren();
        return response()->json($permissions);
    }
}