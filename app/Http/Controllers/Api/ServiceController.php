<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ServiceCrudService;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceCrudService $serviceCrud) {}

    public function list(Request $request)
    {
        // If authenticated as admin, owner, manager, or staff, they can see inactive
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $includeInactive = in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        $services = $this->serviceCrud->getAll($includeInactive);

        return response()->json([
            'success' => true,
            'message' => 'Services retrieved.',
            'data' => ['services' => $services],
        ]);
    }

    public function get(Request $request, int $id)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $requireActive = ! in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        try {
            $service = $this->serviceCrud->getById($id, $requireActive);

            return response()->json([
                'success' => true,
                'message' => 'Service retrieved.',
                'data' => ['service' => $service],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 404);
        }
    }

    public function getBySlug(Request $request, string $slug)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $requireActive = ! in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        try {
            $service = $this->serviceCrud->getBySlug($slug, $requireActive);

            return response()->json([
                'success' => true,
                'message' => 'Service retrieved.',
                'data' => ['service' => $service],
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
            $service = $this->serviceCrud->create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Service created.',
                'data' => ['service' => $service],
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
            $service = $this->serviceCrud->update($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Service updated.',
                'data' => ['service' => $service],
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
            $this->serviceCrud->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Service deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function variationCreate(Request $request, int $serviceId)
    {
        try {
            $variation = $this->serviceCrud->createVariation($serviceId, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Variation created.',
                'data' => ['variation' => $variation],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function variationUpdate(Request $request, int $serviceId, int $variationId)
    {
        try {
            $variation = $this->serviceCrud->updateVariation($serviceId, $variationId, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Variation updated.',
                'data' => ['variation' => $variation],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function variationDelete(Request $request, int $serviceId, int $variationId)
    {
        try {
            $this->serviceCrud->deleteVariation($serviceId, $variationId);

            return response()->json([
                'success' => true,
                'message' => 'Variation deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
