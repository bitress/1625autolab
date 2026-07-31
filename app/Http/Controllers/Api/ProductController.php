<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService) {}

    public function list(Request $request)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $includeInactive = in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        $products = $this->productService->getAll($includeInactive);

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved.',
            'data' => ['products' => $products],
        ]);
    }

    public function get(Request $request, string $id)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $requireActive = ! in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        try {
            $product = $this->productService->getByIdentifier($id, $requireActive);

            return response()->json([
                'success' => true,
                'message' => 'Product retrieved.',
                'data' => ['product' => $product],
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
            $product = $this->productService->create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Product created.',
                'data' => ['product' => $product],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            // Admin only actions get numeric ID typically, but we should resolve it if needed
            // Assuming the route passes ID, let's resolve it.
            $numericId = $this->productService->resolveId($id);
            $product = $this->productService->update($numericId, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Product updated.',
                'data' => ['product' => $product],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $numericId = $this->productService->resolveId($id);
            $this->productService->delete($numericId);

            return response()->json([
                'success' => true,
                'message' => 'Product deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function variationCreate(Request $request, string $id)
    {
        try {
            $numericId = $this->productService->resolveId($id);
            $variation = $this->productService->createVariation($numericId, $request->all());

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

    public function variationUpdate(Request $request, string $id, int $variationId)
    {
        try {
            $numericId = $this->productService->resolveId($id);
            $variation = $this->productService->updateVariation($numericId, $variationId, $request->all());

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

    public function variationDelete(Request $request, string $id, int $variationId)
    {
        try {
            $numericId = $this->productService->resolveId($id);
            $this->productService->deleteVariation($numericId, $variationId);

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
