<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BuildUpdateService;
use Illuminate\Http\Request;

class BuildUpdateController extends Controller
{
    public function __construct(private readonly BuildUpdateService $buildUpdateService) {}

    public function list(Request $request)
    {
        $bookingId = $request->query('bookingId');
        if (! $bookingId) {
            return response()->json([
                'success' => false,
                'message' => 'bookingId is required.',
            ], 400);
        }

        $updates = $this->buildUpdateService->getByBookingId($bookingId);

        return response()->json([
            'success' => true,
            'message' => 'Build updates retrieved.',
            'data' => ['updates' => $updates],
        ]);
    }

    public function create(Request $request)
    {
        $bookingId = $request->input('bookingId');
        $note = $request->input('note', '');
        $photoUrls = $request->input('photoUrls', []);

        if (! $bookingId || (! $note && empty($photoUrls))) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required fields.',
            ], 400);
        }

        try {
            $update = $this->buildUpdateService->create($bookingId, $note, $photoUrls);

            return response()->json([
                'success' => true,
                'message' => 'Build update posted.',
                'data' => ['update' => $update],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
