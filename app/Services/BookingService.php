<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class BookingService
{
    private ?array $activePortfolioCache = null;

    private ?bool $hasBuildSlugColumnCache = null;

    private ?bool $hasTeamMemberUserIdColumnCache = null;

    private ?int $slotCapacityCache = null;

    private const VALID_STATUSES = ['pending', 'confirmed', 'completed', 'cancelled', 'awaiting_parts'];

    public function getSlotCapacity(): int
    {
        if ($this->slotCapacityCache !== null) {
            return $this->slotCapacityCache;
        }

        $settings = app(SiteSettingsService::class)->getAll();
        $this->slotCapacityCache = max(1, (int) ($settings['slot_capacity'] ?? 3));

        return $this->slotCapacityCache;
    }

    public function create(array $data, ?int $userId = null): array
    {
        $this->validatePayload($data);

        $date = trim((string) ($data['appointmentDate'] ?? ''));
        $time = trim((string) ($data['appointmentTime'] ?? ''));
        $waitlistClaimToken = trim((string) ($data['waitlistClaimToken'] ?? ''));
        $claimEntry = null;

        if ($waitlistClaimToken !== '' && class_exists(WaitlistService::class)) {
            $claimEntry = app(WaitlistService::class)->validateClaimForBooking($waitlistClaimToken, [
                'appointmentDate' => $date,
                'appointmentTime' => $time,
                'email' => (string) ($data['email'] ?? ''),
            ]);
        }

        if ($claimEntry === null && in_array($time, $this->getBookedSlots($date), true)) {
            throw new RuntimeException('This time slot is fully booked. Please choose a different time.', 409);
        }

        $serviceIds = $this->resolveServiceIds($data);
        $primaryId = $serviceIds[0];
        $serviceName = $this->resolveServiceNames($serviceIds, $data);

        $id = Str::uuid()->toString();

        $booking = [
            'id' => $id,
            'referenceNumber' => $this->generateReferenceNumber(),
            'userId' => $userId,
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'vehicleInfo' => trim((string) ($data['vehicleInfo'] ?? '')),
            'vehicleMake' => trim((string) ($data['vehicleMake'] ?? '')),
            'vehicleModel' => trim((string) ($data['vehicleModel'] ?? '')),
            'vehicleYear' => trim((string) ($data['vehicleYear'] ?? '')),
            'serviceId' => $primaryId,
            'serviceIds' => $serviceIds,
            'serviceName' => $serviceName,
            'selectedVariations' => $this->resolveSelectedVariations($data),
            'appointmentDate' => $date,
            'appointmentTime' => $time,
            'notes' => trim((string) ($data['notes'] ?? '')),
            'signatureData' => $data['signatureData'] ?? null,
            'mediaUrls' => $data['mediaUrls'] ?? [],
            'beforePhotos' => [],
            'afterPhotos' => [],
            'status' => 'pending',
            'awaitingParts' => false,
            'partsNotes' => null,
            'source' => trim((string) ($data['source'] ?? 'website')),
            'createdAt' => Carbon::now()->toIso8601String(),
        ];

        $insertData = [
            'id' => $booking['id'],
            'reference_number' => $booking['referenceNumber'],
            'user_id' => $booking['userId'],
            'name' => $booking['name'],
            'email' => $booking['email'],
            'phone' => $booking['phone'],
            'vehicle_info' => $booking['vehicleInfo'],
            'vehicle_make' => $booking['vehicleMake'] ?: null,
            'vehicle_model' => $booking['vehicleModel'] ?: null,
            'vehicle_year' => $booking['vehicleYear'] ?: null,
            'service_id' => $booking['serviceId'],
            'service_ids' => json_encode($booking['serviceIds']),
            'selected_variations' => json_encode($booking['selectedVariations']),
            'appointment_date' => $booking['appointmentDate'],
            'appointment_time' => $booking['appointmentTime'],
            'notes' => $booking['notes'] ?: null,
            'signature_data' => $booking['signatureData'],
            'media_urls' => json_encode($booking['mediaUrls']),
            'before_media_urls' => json_encode($booking['beforePhotos']),
            'after_media_urls' => json_encode($booking['afterPhotos']),
            'status' => $booking['status'],
            'source' => $booking['source'],
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        if ($this->hasBuildSlugColumn()) {
            $insertData['build_slug'] = $this->resolveBuildSlugForBooking($booking);
        }

        DB::table('bookings')->insert($insertData);

        $this->addActivity(
            $id,
            'BOOKING_SUBMITTED',
            'Booking submitted',
            'Status: pending',
            $userId,
            $userId !== null ? 'client' : 'system',
            $booking['createdAt']
        );

        if (class_exists(NotificationJobQueueService::class)) {
            $queue = app(NotificationJobQueueService::class);
            $queue->dispatch('booking_created', ['booking' => $booking]);

            $runAfter = NotificationJobQueueService::calculateReminderRunAfter($date, $time, 3);
            if ($runAfter !== null) {
                $queue->dispatch('appointment_reminder_3h', ['data' => $booking], $runAfter);
            }
        }

        if ($waitlistClaimToken !== '' && class_exists(WaitlistService::class)) {
            try {
                app(WaitlistService::class)->markBookedByClaimToken($waitlistClaimToken, $id);
            } catch (\Throwable) {
                // don't block booking creation
            }
        }

        return $booking;
    }

    public function getAll(): array
    {
        return $this->mapDbRows($this->baseQuery()->orderBy('b.created_at', 'desc')->get());
    }

    public function getByUserId(int $userId): array
    {
        return $this->mapDbRows(
            $this->baseQuery()
                ->where('b.user_id', $userId)
                ->orderBy('b.created_at', 'desc')
                ->get()
        );
    }

    public function getStats(): array
    {
        $total = DB::table('bookings')->count();

        $byStatus = DB::table('bookings')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $pending = (int) ($byStatus['pending'] ?? 0);
        $confirmed = (int) ($byStatus['confirmed'] ?? 0);
        $completed = (int) ($byStatus['completed'] ?? 0);
        $cancelled = (int) ($byStatus['cancelled'] ?? 0);

        $thisWeek = DB::table('bookings')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $thisMonth = DB::table('bookings')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        $today = Carbon::now()->toDateString();

        $todayBookings = DB::table('bookings')
            ->where('appointment_date', $today)
            ->count();

        $todayPending = DB::table('bookings')
            ->where('appointment_date', $today)
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        $topServices = DB::table('bookings as b')
            ->join('services as s', 's.id', '=', 'b.service_id')
            ->select('s.title as service_name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('b.service_id', 's.title')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        $peakHours = DB::table('bookings')
            ->select('appointment_time as hour_label', DB::raw('COUNT(*) as cnt'))
            ->whereIn('status', ['pending', 'confirmed', 'completed'])
            ->groupBy('appointment_time')
            ->orderByDesc('cnt')
            ->limit(8)
            ->get()
            ->toArray();

        usort($peakHours, static function ($a, $b): int {
            $timeA = strtotime((string) $a->hour_label);
            $timeB = strtotime((string) $b->hour_label);
            if ($timeA === false && $timeB === false) {
                return 0;
            }
            if ($timeA === false) {
                return 1;
            }
            if ($timeB === false) {
                return -1;
            }

            return $timeA <=> $timeB;
        });

        $reviewRow = Schema::hasTable('booking_reviews')
            ? DB::table('booking_reviews')
                ->where('is_approved', 1)
                ->select(DB::raw('COUNT(*) as total'), DB::raw('COALESCE(AVG(rating), 0) as avg_rating'))
                ->first()
            : null;

        return [
            'totalBookings' => $total,
            'pendingBookings' => $pending,
            'confirmedBookings' => $confirmed,
            'completedBookings' => $completed,
            'cancelledBookings' => $cancelled,
            'activeBookings' => $pending + $confirmed,
            'bookingsThisWeek' => $thisWeek,
            'bookingsThisMonth' => $thisMonth,
            'todayBookings' => $todayBookings,
            'todayPending' => $todayPending,
            'topServices' => $topServices->map(fn ($r) => [
                'name' => $r->service_name,
                'count' => (int) $r->cnt,
            ])->toArray(),
            'peakHours' => array_map(fn ($r) => [
                'time' => (string) $r->hour_label,
                'count' => (int) $r->cnt,
            ], $peakHours),
            'reviewCount' => $reviewRow ? (int) $reviewRow->total : 0,
            'avgRating' => $reviewRow ? (float) $reviewRow->avg_rating : 0.0,
        ];
    }

    public function getBookedSlots(string $date): array
    {
        $slots = DB::table('bookings')
            ->select('appointment_time')
            ->where('appointment_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->groupBy('appointment_time')
            ->having(DB::raw('COUNT(*)'), '>=', $this->getSlotCapacity())
            ->pluck('appointment_time')
            ->toArray();

        return array_map('strval', $slots);
    }

    public function getSlotCounts(string $date): array
    {
        $counts = DB::table('bookings')
            ->select('appointment_time', DB::raw('COUNT(*) as cnt'))
            ->where('appointment_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->groupBy('appointment_time')
            ->get();

        $result = [];
        foreach ($counts as $row) {
            $result[(string) $row->appointment_time] = (int) $row->cnt;
        }

        return $result;
    }

    public function updateStatus(string $id, string $status, ?int $actorUserId = null, string $actorRole = 'system'): array
    {
        if (! in_array($status, self::VALID_STATUSES, true)) {
            throw new RuntimeException('Invalid status. Allowed: '.implode(', ', self::VALID_STATUSES).'.', 422);
        }

        $before = $this->adminFindById($id);
        if ($before === null) {
            throw new RuntimeException('Booking not found.', 404);
        }

        $beforePhotos = is_array($before['beforePhotos'] ?? null) ? $before['beforePhotos'] : [];
        $afterPhotos = is_array($before['afterPhotos'] ?? null) ? $before['afterPhotos'] : [];

        if ($status === 'confirmed' && count($beforePhotos) === 0) {
            throw new RuntimeException('Add at least one before photo before check-in.', 422);
        }
        if ($status === 'completed' && count($afterPhotos) === 0) {
            throw new RuntimeException('Add at least one after photo before completion.', 422);
        }

        DB::table('bookings')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => Carbon::now(),
        ]);

        $updated = $this->adminFindById($id);

        if ($this->hasBuildSlugColumn()) {
            $resolvedSlug = $this->resolveBuildSlugForBooking($updated);
            $currentSlug = trim((string) ($updated['buildSlug'] ?? ''));

            if ($resolvedSlug !== null && $resolvedSlug !== '' && $resolvedSlug !== $currentSlug) {
                DB::table('bookings')->where('id', $id)->update(['build_slug' => $resolvedSlug]);
                $updated['buildSlug'] = $resolvedSlug;
            }
        }

        $fromStatus = (string) ($before['status'] ?? 'unknown');
        $toStatus = (string) ($updated['status'] ?? $status);

        $this->addActivity(
            $id,
            'BOOKING_STATUS_CHANGED',
            'Status changed',
            'From: '.str_replace('_', ' ', $fromStatus).' -> '.str_replace('_', ' ', $toStatus),
            $actorUserId,
            $actorRole
        );

        if (class_exists(NotificationJobQueueService::class)) {
            app(NotificationJobQueueService::class)->dispatch('booking_status_changed', ['booking' => $updated]);
        }

        if ($status === 'cancelled') {
            $slotDate = (string) ($updated['appointmentDate'] ?? '');
            $slotTime = (string) ($updated['appointmentTime'] ?? '');
            if ($slotDate !== '' && $slotTime !== '' && class_exists(WaitlistService::class)) {
                try {
                    app(WaitlistService::class)->handleBookingCancelled($slotDate, $slotTime);
                } catch (\Throwable) {
                    // fail silently
                }
            }
        }

        return $updated;
    }

    public function updateQaPhotos(string $id, string $stage, array $photoUrls, ?int $actorUserId = null): array
    {
        if (! in_array($stage, ['before', 'after'], true)) {
            throw new RuntimeException('Invalid QA stage. Use "before" or "after".', 422);
        }

        $cleanUrls = array_values(array_filter(
            array_map(static fn ($v) => is_string($v) ? trim($v) : '', $photoUrls),
            static fn (string $u) => $u !== ''
        ));

        if (count($cleanUrls) === 0) {
            throw new RuntimeException('At least one photo is required.', 422);
        }

        $column = $stage === 'before' ? 'before_media_urls' : 'after_media_urls';

        DB::table('bookings')->where('id', $id)->update([
            $column => json_encode($cleanUrls),
            'updated_at' => Carbon::now(),
        ]);

        $updated = $this->adminFindById($id);
        if (! $updated) {
            throw new RuntimeException('Booking not found.', 404);
        }

        $this->addActivity(
            $id,
            $stage === 'before' ? 'BOOKING_BEFORE_PHOTOS_UPDATED' : 'BOOKING_AFTER_PHOTOS_UPDATED',
            $stage === 'before' ? 'Before photos updated' : 'After photos updated',
            count($cleanUrls).' photo(s)',
            $actorUserId,
            'admin'
        );

        return $updated;
    }

    public function updatePartsStatus(string $id, bool $awaitingParts, string $partsNotes, ?int $actorUserId = null, string $actorRole = 'admin'): array
    {
        DB::table('bookings')->where('id', $id)->update([
            'awaiting_parts' => $awaitingParts ? 1 : 0,
            'parts_notes' => $partsNotes,
            'updated_at' => Carbon::now(),
        ]);

        $updated = $this->adminFindById($id);
        if (! $updated) {
            throw new RuntimeException('Booking not found.', 404);
        }

        $partsDetail = trim($partsNotes);
        $this->addActivity(
            $id,
            'BOOKING_PARTS_UPDATED',
            $awaitingParts ? 'Flagged: Awaiting Parts' : 'Parts status updated',
            $partsDetail !== '' ? $partsDetail : null,
            $actorUserId,
            $actorRole
        );

        if ($awaitingParts && class_exists(NotificationJobQueueService::class)) {
            app(NotificationJobQueueService::class)->dispatch('booking_awaiting_parts', ['booking' => $updated]);
        }

        return $updated;
    }

    public function updateInternalNotes(string $id, string $notes, ?int $actorUserId = null, string $actorRole = 'admin'): array
    {
        DB::table('bookings')->where('id', $id)->update([
            'internal_notes' => $notes,
            'updated_at' => Carbon::now(),
        ]);

        $updated = $this->adminFindById($id);
        if (! $updated) {
            throw new RuntimeException('Booking not found.', 404);
        }

        $this->addActivity(
            $id,
            'BOOKING_INTERNAL_NOTES_UPDATED',
            'Internal notes updated',
            null,
            $actorUserId,
            $actorRole
        );

        return $updated;
    }

    public function assignTechnician(string $id, ?int $assignedTechId, ?int $actorUserId = null, string $actorRole = 'admin'): array
    {
        $before = $this->adminFindById($id);
        if ($before === null) {
            throw new RuntimeException('Booking not found.', 404);
        }

        $techName = null;
        $techPhone = '';
        $techEmail = '';
        $techUserId = null;

        if ($assignedTechId !== null) {
            $techQuery = DB::table('team_members')->where('id', $assignedTechId);
            if ($this->hasTeamMemberUserIdColumn()) {
                $techQuery->select('id', 'name', 'phone', 'email', 'user_id');
            } else {
                $techQuery->select('id', 'name', 'phone', 'email');
            }

            $tech = $techQuery->first();
            if (! $tech) {
                throw new RuntimeException('Technician not found.', 404);
            }

            $techName = (string) ($tech->name ?? '');
            $techPhone = (string) ($tech->phone ?? '');
            $techEmail = (string) ($tech->email ?? '');

            if (isset($tech->user_id) && $tech->user_id !== null) {
                $techUserId = (int) $tech->user_id;
            }
            if ($techUserId === null) {
                $techUserId = $this->resolveTeamMemberUserId((array) $tech);
            }
        }

        DB::table('bookings')->where('id', $id)->update([
            'assigned_tech_id' => $assignedTechId,
            'updated_at' => Carbon::now(),
        ]);

        $updated = $this->adminFindById($id);

        $beforeId = isset($before['assignedTechId']) && $before['assignedTechId'] !== null
            ? (int) $before['assignedTechId']
            : null;

        $beforeTechUserId = null;
        if (isset($before['assignedTech']) && is_array($before['assignedTech'])) {
            $rawBeforeTechUserId = $before['assignedTech']['userId'] ?? null;
            if ($rawBeforeTechUserId !== null) {
                $beforeTechUserId = (int) $rawBeforeTechUserId;
            }
        }

        if ($beforeTechUserId === null && $beforeId !== null) {
            $beforeTechRow = $this->loadTeamMemberById($beforeId);
            if ($beforeTechRow !== null) {
                $beforeTechUserId = $this->resolveTeamMemberUserId($beforeTechRow);
            }
        }

        if ($beforeId !== $assignedTechId) {
            $reference = (string) ($updated['referenceNumber'] ?? $id);

            if ($assignedTechId === null) {
                $this->addActivity(
                    $id,
                    'BOOKING_TECHNICIAN_UNASSIGNED',
                    'Technician unassigned',
                    null,
                    $actorUserId,
                    $actorRole
                );

                if ($beforeTechUserId !== null && class_exists(NotificationPreferencesService::class) && class_exists(UserNotificationService::class)) {
                    if (app(NotificationPreferencesService::class)->inappEnabled($beforeTechUserId, 'assignment')) {
                        app(UserNotificationService::class)->createForUser(
                            $beforeTechUserId,
                            'assignment',
                            'Booking Unassigned',
                            'You were unassigned from booking '.$reference.'.',
                            ['bookingId' => $id]
                        );
                    }
                }
            } else {
                $this->addActivity(
                    $id,
                    'BOOKING_TECHNICIAN_ASSIGNED',
                    'Technician assigned',
                    $techName !== '' ? $techName : ('ID '.$assignedTechId),
                    $actorUserId,
                    $actorRole
                );

                if ($beforeTechUserId !== null && $beforeTechUserId !== $techUserId && class_exists(NotificationPreferencesService::class) && class_exists(UserNotificationService::class)) {
                    if (app(NotificationPreferencesService::class)->inappEnabled($beforeTechUserId, 'assignment')) {
                        app(UserNotificationService::class)->createForUser(
                            $beforeTechUserId,
                            'assignment',
                            'Booking Reassigned',
                            'Booking '.$reference.' was reassigned to another technician.',
                            ['bookingId' => $id]
                        );
                    }
                }

                if ($techUserId !== null && class_exists(NotificationPreferencesService::class) && class_exists(UserNotificationService::class)) {
                    if (app(NotificationPreferencesService::class)->inappEnabled($techUserId, 'assignment')) {
                        app(UserNotificationService::class)->createForUser(
                            $techUserId,
                            'assignment',
                            'New Booking Assignment',
                            'You were assigned to booking '.$reference.'.',
                            ['bookingId' => $id]
                        );
                    }
                }

                if (($techPhone !== '' || $techEmail !== '') && class_exists(NotificationJobQueueService::class)) {
                    app(NotificationJobQueueService::class)->dispatch('staff_assignment_sms_email', [
                        'booking' => $updated,
                        'techPhone' => $techPhone,
                        'techEmail' => $techEmail,
                        'techName' => $techName ?? '',
                        'techUserId' => $techUserId,
                    ]);
                }
            }
        }

        return $updated;
    }

    public function getCustomerStats(int $userId): array
    {
        $stats = DB::table('bookings')
            ->select(
                DB::raw('COUNT(*) AS total'),
                DB::raw("SUM(status = 'completed') AS completed"),
                DB::raw('MIN(created_at) AS first_booking')
            )
            ->where('user_id', $userId)
            ->first();

        return [
            'totalVisits' => (int) ($stats->total ?? 0),
            'completedVisits' => (int) ($stats->completed ?? 0),
            'memberSince' => $stats->first_booking ? (string) $stats->first_booking : null,
        ];
    }

    public function cancelByUser(string $id, int $userId): array
    {
        $booking = $this->adminFindById($id);
        if ($booking === null) {
            throw new RuntimeException('Booking not found.', 404);
        }

        if ((int) ($booking['userId'] ?? 0) !== $userId) {
            throw new RuntimeException('You are not authorized to cancel this booking.', 403);
        }

        if (! in_array($booking['status'], ['pending', 'confirmed'], true)) {
            throw new RuntimeException('Only pending or confirmed bookings can be cancelled.', 422);
        }

        return $this->updateStatus($id, 'cancelled', $userId, 'client');
    }

    public function updateCalibrationData(string $id, array $data, ?int $actorUserId = null, string $actorRole = 'admin'): array
    {
        $booking = $this->adminFindById($id);
        if ($booking === null) {
            throw new RuntimeException('Booking not found.', 404);
        }

        DB::table('bookings')->where('id', $id)->update([
            'calibration_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'updated_at' => Carbon::now(),
        ]);

        $this->addActivity(
            $id,
            'BOOKING_CALIBRATION_UPDATED',
            'Calibration data updated',
            null,
            $actorUserId,
            $actorRole
        );

        return $this->adminFindById($id) ?? $booking;
    }

    public function getById(string $id, int $userId): array
    {
        $booking = $this->adminFindById($id);
        if ($booking === null) {
            throw new RuntimeException('Booking not found.', 404);
        }

        if ((int) ($booking['userId'] ?? 0) !== $userId) {
            throw new RuntimeException('You are not authorized to view this booking.', 403);
        }

        return $booking;
    }

    public function adminFindById(string $id): ?array
    {
        $row = $this->baseQuery()->where('b.id', $id)->first();

        return $row ? $this->mapDbRow($row) : null;
    }

    public function delete(string $id): void
    {
        $existing = $this->adminFindById($id);
        if ($existing === null) {
            throw new RuntimeException('Booking not found.', 404);
        }

        DB::transaction(function () use ($id) {
            if (Schema::hasTable('booking_reviews')) {
                DB::table('booking_reviews')->where('booking_id', $id)->delete();
            }

            $deleted = DB::table('bookings')->where('id', $id)->delete();
            if ($deleted === 0) {
                throw new RuntimeException('Booking not found.', 404);
            }
        });

        try {
            if (function_exists('activity')) {
                $logger = activity()
                    ->forSubject('bookings', $id)
                    ->withProperties(['before' => $existing]);

                $actorUserId = $this->resolveActorUserId();
                if ($actorUserId !== null) {
                    $logger->byUser($actorUserId);
                }

                $logger->log('deleted', 'bookings');
            }
        } catch (\Throwable $e) {
            error_log('[BookingService] Failed to write booking delete activity log: '.$e->getMessage());
        }
    }

    public function adminReschedule(string $id, string $date, string $time, ?int $actorUserId = null, string $actorRole = 'admin'): array
    {
        $booking = $this->adminFindById($id);
        if ($booking === null) {
            throw new RuntimeException('Booking not found.', 404);
        }

        $oldDate = (string) ($booking['appointmentDate'] ?? '');
        $oldTime = (string) ($booking['appointmentTime'] ?? '');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('Invalid date format. Expected YYYY-MM-DD.', 422);
        }

        $inputTs = strtotime($date);
        if ($inputTs === false) {
            throw new RuntimeException('Invalid date. Expected a valid YYYY-MM-DD date.', 422);
        }

        $todayTs = strtotime(Carbon::now()->toDateString());
        if ($inputTs < $todayTs) {
            throw new RuntimeException('Appointment date cannot be in the past.', 422);
        }
        if (trim($time) === '') {
            throw new RuntimeException('Appointment time is required.', 422);
        }

        $sameSlot = ($booking['appointmentDate'] === $date && $booking['appointmentTime'] === $time);
        if (! $sameSlot && in_array($time, $this->getBookedSlots($date), true)) {
            throw new RuntimeException('This time slot is fully booked. Please choose a different time.', 409);
        }

        DB::table('bookings')->where('id', $id)->update([
            'appointment_date' => $date,
            'appointment_time' => $time,
            'updated_at' => Carbon::now(),
        ]);

        $updated = $this->adminFindById($id);

        $this->addActivity(
            $id,
            'BOOKING_APPOINTMENT_RESCHEDULED',
            'Rescheduled',
            sprintf('%s %s -> %s %s', $oldDate, $oldTime, $date, $time),
            $actorUserId,
            $actorRole
        );

        if (($oldDate !== '' && $oldTime !== '') && ($oldDate !== $date || $oldTime !== $time) && class_exists(WaitlistService::class)) {
            try {
                app(WaitlistService::class)->handleBookingCancelled($oldDate, $oldTime);
            } catch (\Throwable) {
                // fail silently
            }
        }

        return $updated;
    }

    public function reschedule(string $id, int $userId, string $date, string $time): array
    {
        $booking = $this->adminFindById($id);
        if ($booking === null) {
            throw new RuntimeException('Booking not found.', 404);
        }

        if ((int) ($booking['userId'] ?? 0) !== $userId) {
            throw new RuntimeException('You are not authorized to reschedule this booking.', 403);
        }

        if (! in_array($booking['status'], ['pending', 'confirmed'], true)) {
            throw new RuntimeException('Only pending or confirmed bookings can be rescheduled.', 422);
        }

        return $this->adminReschedule($id, $date, $time, $userId, 'client');
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    private function baseQuery()
    {
        $query = DB::table('bookings as b')
            ->leftJoin('services as s', 's.id', '=', 'b.service_id')
            ->leftJoin('team_members as tm', 'tm.id', '=', 'b.assigned_tech_id')
            ->select('b.*', 's.title as service_name', 'tm.name as assigned_tech_name', 'tm.role as assigned_tech_role', 'tm.image_url as assigned_tech_image_url');

        if ($this->hasTeamMemberUserIdColumn()) {
            $query->addSelect('tm.user_id as assigned_tech_user_id');
        } else {
            $query->selectRaw('NULL as assigned_tech_user_id');
        }

        return $query;
    }

    private function mapDbRows($rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = $this->mapDbRow($row);
        }

        return $mapped;
    }

    private function mapDbRow($row): array
    {
        $row = (array) $row;

        $rawIds = $row['service_ids'] ?? null;
        $serviceIds = $rawIds
            ? (json_decode((string) $rawIds, true) ?? [(int) $row['service_id']])
            : [(int) $row['service_id']];

        $rawMedia = $row['media_urls'] ?? null;
        $mediaUrls = $rawMedia ? (json_decode((string) $rawMedia, true) ?? []) : [];

        $rawBeforeMedia = $row['before_media_urls'] ?? null;
        $beforePhotos = $rawBeforeMedia ? (json_decode((string) $rawBeforeMedia, true) ?? []) : [];

        $rawAfterMedia = $row['after_media_urls'] ?? null;
        $afterPhotos = $rawAfterMedia ? (json_decode((string) $rawAfterMedia, true) ?? []) : [];

        $rawVars = $row['selected_variations'] ?? null;
        $selectedVariations = $rawVars ? (json_decode((string) $rawVars, true) ?? []) : [];

        $assignedTechId = isset($row['assigned_tech_id']) && $row['assigned_tech_id'] !== null
            ? (int) $row['assigned_tech_id']
            : null;

        $assignedTech = null;
        if ($assignedTechId !== null) {
            $assignedTech = [
                'id' => $assignedTechId,
                'userId' => isset($row['assigned_tech_user_id']) && $row['assigned_tech_user_id'] !== null ? (int) $row['assigned_tech_user_id'] : null,
                'name' => (string) ($row['assigned_tech_name'] ?? ''),
                'role' => (string) ($row['assigned_tech_role'] ?? ''),
                'imageUrl' => $row['assigned_tech_image_url'] ?? null,
            ];
        }

        $mapped = [
            'id' => $row['id'],
            'referenceNumber' => $row['reference_number'],
            'userId' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
            'assignedTechId' => $assignedTechId,
            'assignedTech' => $assignedTech,
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'vehicleInfo' => $row['vehicle_info'],
            'vehicleMake' => $row['vehicle_make'] ?? null,
            'vehicleModel' => $row['vehicle_model'] ?? null,
            'vehicleYear' => $row['vehicle_year'] ?? null,
            'serviceId' => (int) $row['service_id'],
            'serviceIds' => $serviceIds,
            'serviceName' => $row['service_name'] ?? null,
            'selectedVariations' => $selectedVariations,
            'appointmentDate' => $row['appointment_date'],
            'appointmentTime' => $row['appointment_time'],
            'notes' => $row['notes'] ?? '',
            'signatureData' => $row['signature_data'] ?? null,
            'mediaUrls' => $mediaUrls,
            'beforePhotos' => $beforePhotos,
            'afterPhotos' => $afterPhotos,
            'status' => $row['status'],
            'source' => $row['source'] ?? 'website',
            'awaitingParts' => (bool) ($row['awaiting_parts'] ?? false),
            'partsNotes' => $row['parts_notes'] ?? null,
            'internalNotes' => $row['internal_notes'] ?? null,
            'calibrationData' => isset($row['calibration_data']) && $row['calibration_data'] !== null
                                      ? json_decode((string) $row['calibration_data'], true)
                                      : null,
            'createdAt' => $row['created_at'],
        ];

        $mapped['buildSlug'] = $this->resolveBuildSlugForBooking($mapped);

        return $mapped;
    }

    private function hasTeamMemberUserIdColumn(): bool
    {
        if ($this->hasTeamMemberUserIdColumnCache !== null) {
            return $this->hasTeamMemberUserIdColumnCache;
        }

        $this->hasTeamMemberUserIdColumnCache = Schema::hasColumn('team_members', 'user_id');

        return $this->hasTeamMemberUserIdColumnCache;
    }

    private function hasBuildSlugColumn(): bool
    {
        if ($this->hasBuildSlugColumnCache !== null) {
            return $this->hasBuildSlugColumnCache;
        }

        $this->hasBuildSlugColumnCache = Schema::hasColumn('bookings', 'build_slug');

        return $this->hasBuildSlugColumnCache;
    }

    private function resolveBuildSlugForBooking(array $booking): ?string
    {
        $status = strtolower((string) ($booking['status'] ?? ''));
        if ($status !== 'completed') {
            return null;
        }

        $explicit = trim((string) ($booking['buildSlug'] ?? ($booking['build_slug'] ?? '')));
        if ($explicit !== '') {
            $slug = $this->makeSlug($explicit);

            return $slug !== '' ? $slug : null;
        }

        $items = $this->getActivePortfolioItems();

        $referenceNumber = trim((string) ($booking['referenceNumber'] ?? ($booking['reference_number'] ?? '')));
        if ($referenceNumber !== '' && ! empty($items)) {
            $needle = strtolower($referenceNumber);
            foreach ($items as $item) {
                $haystack = strtolower(
                    trim((string) ($item['title'] ?? '')).' '.trim((string) ($item['description'] ?? ''))
                );
                if ($haystack !== '' && str_contains($haystack, $needle)) {
                    $slug = $this->portfolioItemSlug($item);
                    if ($slug !== '') {
                        return $slug;
                    }
                }
            }
        }

        $serviceName = trim((string) ($booking['serviceName'] ?? ($booking['service_name'] ?? '')));
        if ($serviceName !== '' && ! empty($items)) {
            $serviceSlug = $this->makeSlug($serviceName);
            if ($serviceSlug !== '') {
                foreach ($items as $item) {
                    $slug = $this->portfolioItemSlug($item);
                    if ($slug === $serviceSlug) {
                        return $slug;
                    }
                }
            }

            foreach ($items as $item) {
                $title = trim((string) ($item['title'] ?? ''));
                if ($title !== '' && stripos($title, $serviceName) !== false) {
                    $slug = $this->portfolioItemSlug($item);
                    if ($slug !== '') {
                        return $slug;
                    }
                }
            }
        }

        if ($referenceNumber !== '') {
            $fallback = $this->makeSlug($referenceNumber);
            if ($fallback !== '') {
                return $fallback;
            }
        }

        $id = trim((string) ($booking['id'] ?? ''));
        if ($id !== '') {
            $fallback = $this->makeSlug($id);
            if ($fallback !== '') {
                return 'booking-'.$fallback;
            }
        }

        return null;
    }

    private function getActivePortfolioItems(): array
    {
        if ($this->activePortfolioCache !== null) {
            return $this->activePortfolioCache;
        }

        if (class_exists(PortfolioService::class)) {
            try {
                $items = app(PortfolioService::class)->getAll(false);
                $this->activePortfolioCache = is_array($items) ? $items : [];
            } catch (\Throwable) {
                $this->activePortfolioCache = [];
            }
        } else {
            $this->activePortfolioCache = [];
        }

        return $this->activePortfolioCache;
    }

    private function makeSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private function portfolioItemSlug(array $item): string
    {
        $explicit = trim((string) ($item['slug'] ?? ''));
        if ($explicit !== '') {
            return $this->makeSlug($explicit);
        }

        return $this->makeSlug((string) ($item['title'] ?? ''));
    }

    private function validatePayload(array $data): void
    {
        $required = [
            'name', 'email', 'phone', 'vehicleInfo',
            'appointmentDate', 'appointmentTime',
        ];
        foreach ($required as $field) {
            if (empty(trim((string) ($data[$field] ?? '')))) {
                throw new RuntimeException("Field '$field' is required.", 422);
            }
        }
        if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email address is required.', 422);
        }

        $ids = $this->resolveServiceIds($data);
        if (empty($ids)) {
            throw new RuntimeException('At least one valid serviceId is required.', 422);
        }
    }

    private function resolveServiceIds(array $data): array
    {
        if (! empty($data['serviceIds']) && is_array($data['serviceIds'])) {
            return array_values(array_filter(
                array_map('intval', $data['serviceIds']),
                fn (int $id) => $id > 0
            ));
        }
        $id = (int) ($data['serviceId'] ?? 0);

        return $id > 0 ? [$id] : [];
    }

    private function resolveServiceNames(array $serviceIds, array $data): string
    {
        if (! empty($serviceIds)) {
            $map = DB::table('services')->whereIn('id', $serviceIds)->pluck('title', 'id')->toArray();

            $names = [];
            foreach ($serviceIds as $id) {
                if (! isset($map[$id])) {
                    throw new RuntimeException("Service #$id not found.", 422);
                }
                $names[] = $map[$id];
            }

            return implode(', ', $names);
        }

        return trim((string) ($data['serviceName'] ?? ''));
    }

    private function resolveSelectedVariations(array $data): array
    {
        $raw = $data['selectedVariations'] ?? [];
        if (! is_array($raw)) {
            return [];
        }
        $result = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $serviceId = isset($item['serviceId']) ? (int) $item['serviceId'] : null;
            $variationId = isset($item['variationId']) ? (int) $item['variationId'] : null;
            $name = isset($item['variationName']) ? trim((string) $item['variationName']) : '';
            if ($serviceId && $variationId) {
                $result[] = [
                    'serviceId' => $serviceId,
                    'variationId' => $variationId,
                    'variationName' => $name,
                ];
            }
        }

        return $result;
    }

    private function generateReferenceNumber(): string
    {
        $date = Carbon::now()->format('Ymd');
        $randomPart = str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        return 'BK-'.$date.'-'.$randomPart;
    }

    private function addActivity(
        string $bookingId,
        string $eventType,
        string $action,
        ?string $detail = null,
        ?int $actorUserId = null,
        string $actorRole = 'system',
        ?string $createdAt = null
    ): void {
        try {
            if (class_exists(BookingActivityService::class)) {
                app(BookingActivityService::class)->add(
                    $bookingId,
                    $eventType,
                    $action,
                    $detail,
                    $actorUserId,
                    $actorRole,
                    $createdAt
                );
            }

            if (function_exists('activity')) {
                $logger = activity()
                    ->forSubject('bookings', $bookingId)
                    ->withProperties([
                        'eventType' => $eventType,
                        'action' => $action,
                        'detail' => $detail,
                        'actorRole' => $actorRole,
                        'createdAt' => $createdAt,
                    ]);

                if ($actorUserId !== null && $actorUserId > 0) {
                    $logger->byUser($actorUserId);
                }

                $logger->log($eventType, 'bookings');
            }
        } catch (\Throwable $e) {
            error_log('[BookingService] Failed to write booking activity log: '.$e->getMessage());
        }
    }

    private function resolveActorUserId(): ?int
    {
        try {
            $user = Auth::user();

            return $user ? (int) $user->id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function loadTeamMemberById(int $teamMemberId): ?array
    {
        if ($teamMemberId <= 0) {
            return null;
        }

        $query = DB::table('team_members')->where('id', $teamMemberId);
        if ($this->hasTeamMemberUserIdColumn()) {
            $query->select('id', 'name', 'phone', 'email', 'user_id');
        } else {
            $query->select('id', 'name', 'phone', 'email');
        }

        $row = $query->first();

        return $row ? (array) $row : null;
    }

    private function resolveTeamMemberUserId(array $teamMember): ?int
    {
        if (isset($teamMember['user_id']) && $teamMember['user_id'] !== null) {
            $id = (int) $teamMember['user_id'];

            return $id > 0 ? $id : null;
        }

        $email = strtolower(trim((string) ($teamMember['email'] ?? '')));
        if ($email === '') {
            return null;
        }

        $id = DB::table('users')->where('email', $email)->value('id');

        return $id ? (int) $id : null;
    }
}
