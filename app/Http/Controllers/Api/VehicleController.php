<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VehicleService;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function __construct(private readonly VehicleService $vehicleService) {}

    public function makes(Request $request)
    {
        $year = $request->query('year') ? (int) $request->query('year') : null;

        try {
            $makes = $this->vehicleService->getMakes($year);

            return response()->json([
                'success' => true,
                'message' => 'Makes retrieved.',
                'data' => ['makes' => $makes],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function models(Request $request)
    {
        $make = $request->query('make', '');
        $year = $request->query('year') ? (int) $request->query('year') : null;

        if (! $make) {
            return response()->json([
                'success' => false,
                'message' => 'Make is required.',
            ], 400);
        }

        try {
            $models = $this->vehicleService->getModels((string) $make, $year);

            return response()->json([
                'success' => true,
                'message' => 'Models retrieved.',
                'data' => ['models' => $models],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function trims(Request $request)
    {
        $make = $request->query('make', '');
        $model = $request->query('model', '');
        $limit = $request->query('limit') ? (int) $request->query('limit') : 20;
        $page = $request->query('page') ? (int) $request->query('page') : 1;

        if (! $make || ! $model) {
            return response()->json([
                'success' => false,
                'message' => 'Make and model are required.',
            ], 400);
        }

        try {
            $trims = $this->vehicleService->getTrims((string) $make, (string) $model, $limit, $page);

            return response()->json([
                'success' => true,
                'message' => 'Trims retrieved.',
                'data' => ['trims' => $trims],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
