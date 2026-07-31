<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use App\Services\UserService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly PermissionService $permissionService
    ) {}

    public function list(Request $request)
    {
        $roles = $this->userService->listRoles();

        return response()->json([
            'roles' => $roles,
        ]);
    }

    public function auditList(Request $request)
    {
        $limit = $request->query('limit') ? (int) $request->query('limit') : 50;
        $logs = $this->userService->listRoleAuditLogs($limit);

        return response()->json([
            'logs' => $logs,
        ]);
    }

    public function create(Request $request)
    {
        try {
            $role = $this->userService->createRole(
                $request->all(),
                $request->user()->id,
                $request->user()->name ?? ''
            );

            // Flush permission cache
            $this->permissionService->flushCache();

            return response()->json([
                'role' => $role,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $role = $this->userService->updateRoleDefinition(
                $id,
                $request->all(),
                $request->user()->id,
                $request->user()->name ?? ''
            );

            // Flush permission cache
            $this->permissionService->flushCache();

            return response()->json([
                'role' => $role,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function delete(Request $request, int $id)
    {
        try {
            $this->userService->deleteRole(
                $id,
                $request->user()->id,
                $request->user()->name ?? ''
            );

            // Flush permission cache
            $this->permissionService->flushCache();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
