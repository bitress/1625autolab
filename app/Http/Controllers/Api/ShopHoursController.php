<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShopHoursService;
use Illuminate\Http\Request;

class ShopHoursController extends Controller
{
    public function __construct(private readonly ShopHoursService $shopHoursService) {}

    public function get(Request $request)
    {
        $hours = $this->shopHoursService->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Shop hours retrieved.',
            'data' => ['hours' => $hours],
        ]);
    }

    public function update(Request $request)
    {
        try {
            $hours = $this->shopHoursService->updateAll($request->input('hours', []));

            return response()->json([
                'success' => true,
                'message' => 'Shop hours updated.',
                'data' => ['hours' => $hours],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function closedDatesGet(Request $request)
    {
        $dates = $this->shopHoursService->getClosedDates();

        return response()->json([
            'success' => true,
            'message' => 'Closed dates retrieved.',
            'data' => ['dates' => $dates],
        ]);
    }

    public function closedDatesAdd(Request $request)
    {
        try {
            $this->shopHoursService->addClosedDate(
                (string) $request->input('date', ''),
                $request->input('reason'),
                (bool) $request->input('isYearly', false)
            );

            return response()->json([
                'success' => true,
                'message' => 'Closed date added.',
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function closedDatesRemove(Request $request, string $date)
    {
        try {
            $this->shopHoursService->removeClosedDate($date);

            return response()->json([
                'success' => true,
                'message' => 'Closed date removed.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
