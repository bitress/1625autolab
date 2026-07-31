<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\VehicleCatalogService;
use Illuminate\Http\Request;

class VehicleCatalogController extends Controller
{
    public function __construct(private readonly VehicleCatalogService $catalogService) {}

    public function makesList(Request $request)
    {
        $makes = $this->catalogService->listMakes();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle makes retrieved.',
            'data' => ['makes' => $makes],
        ]);
    }

    public function makesCreate(Request $request)
    {
        try {
            $id = $this->catalogService->createMake((string) $request->input('name', ''));

            return response()->json([
                'success' => true,
                'message' => 'Vehicle make created.',
                'data' => ['id' => $id],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function makesUpdate(Request $request, int $id)
    {
        try {
            $this->catalogService->updateMake($id, (string) $request->input('name', ''));

            return response()->json([
                'success' => true,
                'message' => 'Vehicle make updated.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function makesDelete(Request $request, int $id)
    {
        try {
            $this->catalogService->deleteMake($id);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle make deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function modelsList(Request $request)
    {
        $make = $request->query('make', '');

        try {
            $models = $this->catalogService->listModelsByMakeName((string) $make);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle models retrieved.',
                'data' => ['models' => $models],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function modelsCreate(Request $request)
    {
        try {
            $makeId = (int) $request->input('make_id', 0);
            $name = (string) $request->input('name', '');
            $id = $this->catalogService->createModel($makeId, $name);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle model created.',
                'data' => ['id' => $id],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function modelsUpdate(Request $request, int $id)
    {
        try {
            $makeId = (int) $request->input('make_id', 0);
            $name = (string) $request->input('name', '');
            $this->catalogService->updateModel($id, $makeId, $name);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle model updated.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function modelsDelete(Request $request, int $id)
    {
        try {
            $this->catalogService->deleteModel($id);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle model deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
