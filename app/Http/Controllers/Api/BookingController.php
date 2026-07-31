<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Booking\CreateBookingRequest;
use App\Http\Requests\Api\Booking\RescheduleBookingRequest;
use App\Services\BookingAccessService;
use App\Services\BookingActivityService;
use App\Services\BookingService;
use App\Services\TurnstileService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly BookingAccessService $accessService,
        private readonly BookingActivityService $activityService,
        private readonly TurnstileService $turnstile
    ) {}

    public function create(CreateBookingRequest $request)
    {
        $this->turnstile->validate($request->all());

        try {
            $userId = $request->user()?->id;
            $booking = $this->bookingService->create($request->validated(), $userId);

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully.',
                'data' => ['booking' => $booking],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function list(Request $request)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $bookings = $this->accessService->getAccessibleBookingsForPayload($payload);

        return response()->json([
            'success' => true,
            'message' => 'Bookings retrieved.',
            'data' => ['bookings' => $bookings],
        ]);
    }

    public function mine(Request $request)
    {
        $bookings = $this->bookingService->getByUserId($request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Your bookings retrieved.',
            'data' => ['bookings' => $bookings],
        ]);
    }

    public function availability(Request $request)
    {
        $date = $request->query('date', '');

        try {
            $slots = $this->bookingService->getBookedSlots($date);
            $capacity = $this->bookingService->getSlotCapacity();
            $counts = $this->bookingService->getSlotCounts($date);

            return response()->json([
                'success' => true,
                'message' => 'Availability retrieved.',
                'data' => [
                    'date' => $date,
                    'slots' => $slots,
                    'capacity' => $capacity,
                    'counts' => $counts,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function get(Request $request, string $id)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];

        try {
            // Check if admin/staff accessing someone else's, or user accessing their own
            if ($this->accessService->isStaffRole($payload) || ($payload['role'] ?? '') === 'admin' || ($payload['role'] ?? '') === 'owner') {
                $booking = $this->accessService->requireBookingVisibilityForPayload($payload, $id);
            } else {
                $booking = $this->bookingService->getById($id, $request->user()->id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Booking retrieved.',
                'data' => ['booking' => $booking],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 404);
        }
    }

    public function updateStatus(Request $request, string $id)
    {
        $payload = $request->user()->toArray();

        try {
            $this->accessService->requireBookingMutationForPayload($payload, $id);
            $status = $request->input('status');

            $booking = $this->bookingService->updateStatus(
                $id,
                $status,
                $request->user()->id,
                $request->user()->role ?? 'staff'
            );

            return response()->json([
                'success' => true,
                'message' => 'Booking status updated.',
                'data' => ['booking' => $booking],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }
    }

    public function assignTech(Request $request, string $id)
    {
        $payload = $request->user()->toArray();

        try {
            $this->accessService->requireBookingMutationForPayload($payload, $id);
            $techId = $request->input('techId');

            $booking = $this->bookingService->assignTechnician(
                $id,
                $techId ? (int) $techId : null,
                $request->user()->id,
                $request->user()->role ?? 'staff'
            );

            return response()->json([
                'success' => true,
                'message' => 'Technician assigned.',
                'data' => ['booking' => $booking],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }
    }

    public function reschedule(RescheduleBookingRequest $request, string $id)
    {
        try {
            $booking = $this->bookingService->reschedule(
                $id,
                $request->user()->id,
                $request->appointmentDate,
                $request->appointmentTime
            );

            return response()->json([
                'success' => true,
                'message' => 'Booking rescheduled successfully.',
                'data' => ['booking' => $booking],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function cancel(Request $request, string $id)
    {
        try {
            $booking = $this->bookingService->cancelByUser($id, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled.',
                'data' => ['booking' => $booking],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function internalNotes(Request $request, string $id)
    {
        $payload = $request->user()->toArray();

        try {
            $this->accessService->requireBookingVisibilityForPayload($payload, $id);

            $booking = $this->bookingService->updateInternalNotes(
                $id,
                $request->input('internalNotes', ''),
                $request->user()->id,
                $request->user()->role ?? 'staff'
            );

            return response()->json([
                'success' => true,
                'message' => 'Internal notes updated.',
                'data' => ['booking' => $booking],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }
    }

    public function activityList(Request $request, string $id)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];

        try {
            $this->accessService->requireBookingVisibilityForPayload($payload, $id);
            $activities = $this->activityService->getForBooking($id);

            return response()->json([
                'success' => true,
                'message' => 'Activity log retrieved.',
                'data' => ['activities' => $activities],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }
    }
}
