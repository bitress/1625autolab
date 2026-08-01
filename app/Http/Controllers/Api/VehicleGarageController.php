<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UploadStorageService;
use App\Services\VehicleCrudService;
use Illuminate\Http\Request;

class VehicleGarageController extends Controller
{
    public function __construct(
        private readonly VehicleCrudService $vehicleCrud,
        private readonly UploadStorageService $uploadService
    ) {}

    public function list(Request $request)
    {
        $vehicles = $this->vehicleCrud->getByUserId($request->user()->id);

        return response()->json([
            'vehicles' => $vehicles,
        ]);
    }

    public function create(Request $request)
    {
        try {
            $vehicle = $this->vehicleCrud->create($request->user()->id, $request->all());

            return response()->json([
                'vehicle' => $vehicle,
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
            $vehicle = $this->vehicleCrud->update($id, $request->user()->id, $request->all());

            return response()->json([
                'vehicle' => $vehicle,
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
            $this->vehicleCrud->delete($id, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle removed from garage.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function mediaUpload(Request $request)
    {
        if (! $request->hasFile('file')) {
            return response()->json([
                'success' => false,
                'message' => 'No file provided.',
            ], 422);
        }

        $file = $request->file('file');

        try {
            UploadStorageService::assertImageFile($file, ['image/jpeg', 'image/png', 'image/webp'], 5);

            $url = $this->uploadService->upload($file, 'vehicles/');

            return response()->json([
                'url' => $url,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
