<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BlogPostService;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function __construct(private readonly BlogPostService $blogService) {}

    public function list(Request $request)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $publishedOnly = ! in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        $posts = $this->blogService->getAll($publishedOnly);

        return response()->json([
            'success' => true,
            'message' => 'Posts retrieved.',
            'data' => ['posts' => $posts],
        ]);
    }

    public function get(Request $request, int $id)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $publishedOnly = ! in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        try {
            $post = $this->blogService->getById($id, $publishedOnly);

            return response()->json([
                'success' => true,
                'message' => 'Post retrieved.',
                'data' => ['post' => $post],
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
            $post = $this->blogService->create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Post created.',
                'data' => ['post' => $post],
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
            $post = $this->blogService->update($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Post updated.',
                'data' => ['post' => $post],
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
            $this->blogService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Post deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
