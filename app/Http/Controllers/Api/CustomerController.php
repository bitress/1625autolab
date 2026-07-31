<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function stats(Request $request)
    {
        try {
            $stats = $this->bookingService->getCustomerStats($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Customer stats retrieved.',
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
