<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OfferService;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function __construct(private readonly OfferService $offerService) {}

    public function list(Request $request)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $includeInactive = in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        $offers = $this->offerService->getAll($includeInactive);

        return response()->json([
            'offers' => $offers,
        ]);
    }

    public function get(Request $request, int $id)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $requireActive = ! in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        try {
            $offer = $this->offerService->getById($id, $requireActive);

            return response()->json([
                'offer' => $offer,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 404);
        }
    }

    public function create(Request $request)
    {
        try {
            $offer = $this->offerService->create($request->all());

            return response()->json([
                'offer' => $offer,
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
            $offer = $this->offerService->update($id, $request->all());

            return response()->json([
                'offer' => $offer,
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
            $this->offerService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Offer deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
