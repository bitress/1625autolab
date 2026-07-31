<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __construct(private readonly StatsService $statsService) {}

    public function dashboard(Request $request)
    {
        try {
            $stats = $this->statsService->getDashboardStats();

            return response()->json([
                'success' => true,
                'message' => 'Dashboard stats retrieved.',
                'data' => ['stats' => $stats],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
