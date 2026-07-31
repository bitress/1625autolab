<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReviewService;
use App\Services\TurnstileService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService,
        private readonly TurnstileService $turnstile
    ) {}

    public function publishedList(Request $request)
    {
        $serviceId = $request->query('serviceId') ? (int) $request->query('serviceId') : null;
        $reviews = $this->reviewService->getPublished($serviceId);

        return response()->json([
            'success' => true,
            'message' => 'Reviews retrieved.',
            'data' => ['reviews' => $reviews],
        ]);
    }

    public function getForBooking(Request $request, string $bookingId)
    {
        $review = $this->reviewService->getForBooking($bookingId);

        return response()->json([
            'success' => true,
            'message' => 'Review retrieved.',
            'data' => ['review' => $review],
        ]);
    }

    public function create(Request $request, string $bookingId)
    {
        $this->turnstile->validate($request->all());

        try {
            $review = $this->reviewService->create($bookingId, $request->user()->id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully. Pending approval.',
                'data' => ['review' => $review],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function list(Request $request)
    {
        $reviews = $this->reviewService->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Reviews retrieved.',
            'data' => ['reviews' => $reviews],
        ]);
    }

    public function approve(Request $request, int $id)
    {
        try {
            $this->reviewService->approve($id);

            return response()->json([
                'success' => true,
                'message' => 'Review approved.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function reject(Request $request, int $id)
    {
        try {
            $this->reviewService->reject($id);

            return response()->json([
                'success' => true,
                'message' => 'Review rejected.',
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
            $this->reviewService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Review deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
