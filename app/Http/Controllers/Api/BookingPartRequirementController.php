<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class BookingPartRequirementController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly BookingService $bookingService
    ) {}

    public function list(Request $request, string $bookingId)
    {
        $requirements = $this->inventoryService->listBookingPartRequirements($bookingId);

        return response()->json([
            'requirements' => $requirements,
        ]);
    }

    public function create(Request $request, string $bookingId)
    {
        try {
            $requirement = $this->inventoryService->createBookingPartRequirement(
                $bookingId,
                $request->all(),
                $request->user()->id
            );

            return response()->json([
                'requirement' => $requirement,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, string $bookingId, int $reqId)
    {
        try {
            $requirement = $this->inventoryService->updateBookingPartRequirement(
                $bookingId,
                $reqId,
                $request->all()
            );

            return response()->json([
                'requirement' => $requirement,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * PATCH /api/bookings/{id}/parts
     * Update the awaitingParts flag and partsNotes on a booking.
     * Called by frontend updateBookingPartsApi ({ awaitingParts, partsNotes }).
     */
    public function partsUpdate(Request $request, string $bookingId)
    {
        try {
            $awaitingParts = (bool) $request->input('awaitingParts', false);
            $partsNotes = (string) $request->input('partsNotes', '');

            // Delegates to BookingService::updatePartsStatus — the correct existing method.
            $booking = $this->bookingService->updatePartsStatus(
                $bookingId,
                $awaitingParts,
                $partsNotes,
                $request->user()->id,
                $request->user()->role ?? 'admin'
            );

            return response()->json(['booking' => $booking]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
