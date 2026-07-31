<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function list(Request $request)
    {
        $filters = $request->except(['page', 'limit']);
        $users = $this->userService->listUsers($filters);

        return response()->json([
            'users' => $users,
        ]);
    }

    public function assignable(Request $request)
    {
        // Assuming 'assignable' returns staff and manager roles who can be assigned to bookings
        $filters = ['assignable' => 'true'];
        $users = $this->userService->listUsers($filters);

        return response()->json([
            'users' => $users,
        ]);
    }

    public function create(Request $request)
    {
        try {
            $user = $this->userService->createByAdmin($request->all());

            return response()->json([
                'user' => $user,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function roleUpdate(Request $request, int $id)
    {
        try {
            $user = $this->userService->updateRole(
                $id,
                (string) $request->input('role', ''),
                $request->user()->id,
                $request->user()->name ?? ''
            );

            return response()->json([
                'user' => $user,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function statusUpdate(Request $request, int $id)
    {
        try {
            $user = $this->userService->updateUserStatus(
                $id,
                filter_var($request->input('isActive'), FILTER_VALIDATE_BOOLEAN)
            );

            return response()->json([
                'user' => $user,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function infoUpdate(Request $request, int $id)
    {
        try {
            $user = $this->userService->updateUserInfo($id, $request->all());

            return response()->json([
                'user' => $user,
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
            $this->userService->deleteByAdmin(
                $id,
                $request->user()->id,
                $request->user()->name ?? ''
            );

            return response()->json([
                'success' => true,
                'message' => 'User deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
