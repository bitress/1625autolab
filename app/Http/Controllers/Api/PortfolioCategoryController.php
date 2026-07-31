<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PortfolioCategoryService;
use Illuminate\Http\Request;

class PortfolioCategoryController extends Controller
{
    public function __construct(private readonly PortfolioCategoryService $categoryService) {}

    public function list(Request $request)
    {
        $categories = $this->categoryService->getAll();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function get(Request $request, int $id)
    {
        try {
            $category = $this->categoryService->getById($id);

            return response()->json([
                'category' => $category,
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
            $category = $this->categoryService->create($request->all());

            return response()->json([
                'category' => $category,
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
            $category = $this->categoryService->update($id, $request->all());

            return response()->json([
                'category' => $category,
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
            $this->categoryService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Category deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
