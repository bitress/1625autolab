<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use App\Services\Customer360Service;
use App\Services\UserService;
use App\Services\VehicleCrudService;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly BookingService $bookingService,
        private readonly VehicleCrudService $vehicleService,
        private readonly Customer360Service $customer360Service
    ) {}

    public function list(Request $request)
    {
        $filters = $request->except(['page', 'limit']);
        $clients = $this->userService->listClients($filters);

        return response()->json([
            'success' => true,
            'message' => 'Clients retrieved.',
            'data' => ['clients' => $clients],
        ]);
    }

    public function bookings(Request $request, int $id)
    {
        $bookings = $this->bookingService->getByUserId($id);

        return response()->json([
            'success' => true,
            'message' => 'Client bookings retrieved.',
            'data' => ['bookings' => $bookings],
        ]);
    }

    public function vehicles(Request $request, int $id)
    {
        $vehicles = $this->vehicleService->getByUserId($id);

        return response()->json([
            'success' => true,
            'message' => 'Client vehicles retrieved.',
            'data' => ['vehicles' => $vehicles],
        ]);
    }

    public function customer360(Request $request, int $id)
    {
        $limit = $request->query('limit') ? (int) $request->query('limit') : 20;

        try {
            $data = $this->customer360Service->getByUserId($id, $limit);

            return response()->json([
                'success' => true,
                'message' => 'Customer 360 data retrieved.',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
