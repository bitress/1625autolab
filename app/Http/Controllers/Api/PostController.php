<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FacebookService;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(private readonly FacebookService $facebookService) {}

    public function index(Request $request)
    {
        try {
            $posts = $this->facebookService->getPosts();

            return response()->json([
                'success' => true,
                'message' => 'Posts retrieved.',
                'data' => ['posts' => $posts],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
