<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BeforeAfterService;
use Illuminate\Http\Request;

class BeforeAfterController extends Controller
{
    public function __construct(private readonly BeforeAfterService $beforeAfterService) {}

    public function list(Request $request)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $includeInactive = in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        $make = $request->query('make', '');
        $model = $request->query('model', '');

        $records = $this->beforeAfterService->getAll($includeInactive, (string) $make, (string) $model);

        return response()->json([
            'success' => true,
            'message' => 'Before/after records retrieved.',
            'data' => ['records' => $records],
        ]);
    }

    public function get(Request $request, int $id)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $requireActive = ! in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        try {
            $record = $this->beforeAfterService->getById($id, $requireActive);

            return response()->json([
                'success' => true,
                'message' => 'Before/after record retrieved.',
                'data' => ['record' => $record],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 404);
        }
    }

    public function create(Request $request)
    {
        try {
            $record = $this->beforeAfterService->create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Before/after record created.',
                'data' => ['record' => $record],
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
            $record = $this->beforeAfterService->update($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Before/after record updated.',
                'data' => ['record' => $record],
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
            $this->beforeAfterService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Before/after record deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
