<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TeamMemberService;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    public function __construct(private readonly TeamMemberService $teamMemberService) {}

    public function list(Request $request)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $activeOnly = ! in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        $members = $this->teamMemberService->getAll($activeOnly);

        return response()->json([
            'success' => true,
            'message' => 'Team members retrieved.',
            'data' => ['members' => $members],
        ]);
    }

    public function create(Request $request)
    {
        try {
            $member = $this->teamMemberService->create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Team member created.',
                'data' => ['member' => $member],
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
            $member = $this->teamMemberService->update($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Team member updated.',
                'data' => ['member' => $member],
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
            $this->teamMemberService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Team member deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
