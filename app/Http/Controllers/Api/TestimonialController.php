<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TestimonialService;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function __construct(private readonly TestimonialService $testimonialService) {}

    public function list(Request $request)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $activeOnly = ! in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        $testimonials = $this->testimonialService->getAll($activeOnly);

        return response()->json([
            'success' => true,
            'message' => 'Testimonials retrieved.',
            'data' => ['testimonials' => $testimonials],
        ]);
    }

    public function create(Request $request)
    {
        try {
            $testimonial = $this->testimonialService->create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Testimonial created.',
                'data' => ['testimonial' => $testimonial],
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
            $testimonial = $this->testimonialService->update($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Testimonial updated.',
                'data' => ['testimonial' => $testimonial],
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
            $this->testimonialService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Testimonial deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
