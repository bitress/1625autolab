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
use App\Services\UploadStorage;
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
                'booking' => $booking,
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
            'bookings' => $bookings,
        ]);
    }

    public function mine(Request $request)
    {
        $bookings = $this->bookingService->getByUserId($request->user()->id);

        return response()->json([
            'bookings' => $bookings,
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
                'date' => $date,
                'slots' => $slots,
                'capacity' => $capacity,
                'counts' => $counts,
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
                'booking' => $booking,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 404);
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
                'booking' => $booking,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 400);
        }
    }

    public function assignTech(Request $request, string $id)
    {
        $payload = $request->user()->toArray();

        try {
            $this->accessService->requireBookingMutationForPayload($payload, $id);
            // Accept both camelCase variants sent by the frontend
            $techId = $request->input('assignedTechId') ?? $request->input('techId');

            $booking = $this->bookingService->assignTechnician(
                $id,
                $techId ? (int) $techId : null,
                $request->user()->id,
                $request->user()->role ?? 'staff'
            );

            return response()->json([
                'booking' => $booking,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 400);
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
                'booking' => $booking,
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
                'booking' => $booking,
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
                'booking' => $booking,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 400);
        }
    }

    public function activityList(Request $request, string $id)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];

        try {
            $this->accessService->requireBookingVisibilityForPayload($payload, $id);
            $activities = $this->activityService->getForBooking($id);

            return response()->json([
                'logs' => $activities,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 400);
        }
    }

    public function externalCreate(Request $request)
    {
        try {
            $data = $request->all();

            // Accept common integration aliases (snake_case and alternate key names).
            $aliases = [
                'service_id' => 'serviceId',
                'service_ids' => 'serviceIds',
                'service_name' => 'serviceName',
                'vehicle_info' => 'vehicleInfo',
                'vehicle_make' => 'vehicleMake',
                'vehicle_model' => 'vehicleModel',
                'vehicle_year' => 'vehicleYear',
                'appointment_date' => 'appointmentDate',
                'appointment_time' => 'appointmentTime',
                'signature_data' => 'signatureData',
            ];
            foreach ($aliases as $from => $to) {
                if (! isset($data[$to]) && isset($data[$from])) {
                    $data[$to] = $data[$from];
                }
            }

            // Preserve note/message style fields as booking notes.
            if (! isset($data['notes'])) {
                foreach (['note', 'message', 'customer_note', 'customer_notes'] as $key) {
                    if (isset($data[$key]) && trim((string) $data[$key]) !== '') {
                        $data['notes'] = (string) $data[$key];
                        break;
                    }
                }
            }

            // Normalize media aliases to mediaUrls expected by BookingService.
            if (! isset($data['mediaUrls'])) {
                foreach (['images', 'imageUrls', 'image_urls', 'photoUrls', 'photo_urls'] as $key) {
                    if (! isset($data[$key])) {
                        continue;
                    }
                    if (is_array($data[$key])) {
                        $data['mediaUrls'] = $data[$key];
                    } elseif (is_string($data[$key]) && trim($data[$key]) !== '') {
                        $data['mediaUrls'] = [trim($data[$key])];
                    }
                    break;
                }
            }

            if (isset($data['mediaUrls']) && is_array($data['mediaUrls'])) {
                $data['mediaUrls'] = array_values(array_filter(
                    array_map(static fn ($v) => is_string($v) ? trim($v) : '', $data['mediaUrls']),
                    static fn (string $v): bool => $v !== ''
                ));
            }

            if (trim((string) ($data['source'] ?? '')) === '') {
                $data['source'] = 'chatbot';
            }

            // Return structured field validation errors before service call.
            $fieldErrors = [];
            if (trim((string) ($data['name'] ?? '')) === '') {
                $fieldErrors['name'] = 'Name is required.';
            }
            if (trim((string) ($data['phone'] ?? '')) === '') {
                $fieldErrors['phone'] = 'Phone is required.';
            }
            if (trim((string) ($data['appointmentDate'] ?? '')) === '') {
                $fieldErrors['appointmentDate'] = 'Appointment date is required.';
            }

            if (! empty($fieldErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $fieldErrors,
                ], 422);
            }

            $booking = $this->bookingService->create($data, null);

            return response()->json([
                'booking' => $booking,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 400);
        }
    }

    /**
     * PATCH /api/bookings/{id}
     * General-purpose status update (used by frontend updateBookingStatusApi).
     */
    public function update(Request $request, string $id)
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

            return response()->json(['booking' => $booking]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 400);
        }
    }

    /**
     * PATCH /api/bookings/{id}/admin-reschedule
     * Admin/staff override reschedule (bypasses user ownership check).
     */
    public function adminReschedule(Request $request, string $id)
    {
        $payload = $request->user()->toArray();

        try {
            $this->accessService->requireBookingMutationForPayload($payload, $id);

            $booking = $this->bookingService->adminReschedule(
                $id,
                (string) $request->input('appointmentDate', ''),
                (string) $request->input('appointmentTime', ''),
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

    /**
     * PATCH /api/bookings/{id}/qa-photos
     * Update before/after QA photo URLs for a booking.
     */
    public function qaPhotosUpdate(Request $request, string $id)
    {
        $payload = $request->user()->toArray();

        try {
            $this->accessService->requireBookingMutationForPayload($payload, $id);

            $stage = (string) $request->input('stage', 'before'); // 'before' | 'after'
            $photoUrls = (array) $request->input('photoUrls', []);

            $booking = $this->bookingService->updateQaPhotos(
                $id,
                $stage,
                $photoUrls,
                $request->user()->id
            );

            return response()->json(['booking' => $booking]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 400);
        }
    }

    /**
     * PATCH /api/bookings/{id}/calibration
     * Update calibration certificate data for a booking.
     */
    public function calibrationUpdate(Request $request, string $id)
    {
        $payload = $request->user()->toArray();

        try {
            $this->accessService->requireBookingMutationForPayload($payload, $id);

            $booking = $this->bookingService->updateCalibrationData(
                $id,
                $request->only(['beamAngle', 'luxOutput', 'notes']),
                $request->user()->id,
                $request->user()->role ?? 'staff'
            );

            return response()->json(['booking' => $booking]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 400);
        }
    }

    /**
     * DELETE /api/bookings/{id}
     * Hard-delete a booking (admin/owner only via access service).
     */
    public function delete(Request $request, string $id)
    {
        $payload = $request->user()->toArray();

        try {
            $this->accessService->requireBookingMutationForPayload($payload, $id);
            $this->bookingService->delete($id);

            return response()->json(['deleted' => true]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 404);
        }
    }

    public function uploadMedia(Request $request)
    {
        if (! $request->hasFile('files')) {
            return response()->json([
                'success' => false,
                'message' => 'No files provided.',
            ], 422);
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        // Read from legacy env or default to 10
        $maxBytes = (defined('UPLOAD_MAX_MB') ? UPLOAD_MAX_MB : 10) * 1024 * 1024;
        $urls = [];
        $storage = new UploadStorage;

        $files = $request->file('files');
        if (! is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if (! $file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File upload error.',
                ], 422);
            }

            $mime = $file->getMimeType();
            $size = $file->getSize();

            if (! in_array($mime, $allowed, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only JPEG, PNG, WebP and GIF images are accepted.',
                ], 422);
            }
            if ($size > $maxBytes) {
                return response()->json([
                    'success' => false,
                    'message' => 'Each file must be under '.(defined('UPLOAD_MAX_MB') ? UPLOAD_MAX_MB : 10).' MB.',
                ], 422);
            }

            $ext = $file->getClientOriginalExtension();
            $filename = bin2hex(random_bytes(16)).'.'.strtolower($ext);

            $urls[] = $storage->upload($file->getPathname(), $filename, $mime, 'bookings/');
        }

        return response()->json(['urls' => $urls]);
    }
}
