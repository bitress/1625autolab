<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InquiryService
{
    private const SLOT_CAPACITY = 3;

    private const SLOT_WINDOW_MINUTES = 5 * 60;

    public function __construct(
        private readonly ShopHoursService $shopHoursService,
        private readonly InquiryActivityService $activity
    ) {}

    public function create(array $data): array
    {
        $normalized = $this->normalizePayload($data);
        $this->validatePayload($normalized);
        $this->assertSlotCapacity($normalized['appointmentDate'], $normalized['appointmentTime']);

        $inquiryId = (string) Str::uuid();
        $now = Carbon::now()->toDateTimeString();

        $inquiry = [
            'id' => $inquiryId,
            'user_id' => $data['userId'] ?? null,
            'full_name' => $normalized['fullName'],
            'address' => $normalized['address'],
            'contact_number' => $normalized['contactNumber'],
            'email_address' => $normalized['emailAddress'],
            'facebook_name' => $normalized['facebookName'],
            'plate_number' => $normalized['plateNumber'],
            'make' => $normalized['make'],
            'model' => $normalized['model'],
            'year_model' => $normalized['yearModel'],
            'product_to_purchase' => $normalized['productToPurchase'],
            'appointment_date' => $normalized['appointmentDate'],
            'appointment_time' => $normalized['appointmentTime'],
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('customer_inquiries')->insert($inquiry);

        $mappedInquiry = $this->mapDbRow($inquiry);
        $this->syncOccupancyForInquiry($mappedInquiry);

        $this->activity->add(
            $inquiryId,
            'created',
            'Inquiry submitted',
            null,
            null,
            'client' // Typically created by client
        );

        return $mappedInquiry;
    }

    public function getAll(): array
    {
        $rows = DB::table('customer_inquiries')
            ->select([
                'id', 'user_id', 'full_name', 'address', 'contact_number', 'email_address', 'facebook_name', 'plate_number',
                'make', 'model', 'year_model', 'product_to_purchase', 'appointment_date',
                'appointment_time', 'status', 'created_at',
            ])
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return array_map(fn ($row) => $this->mapDbRow((array) $row), $rows->all());
    }

    public function getAllForUser($userId): array
    {
        $rows = DB::table('customer_inquiries')
            ->select([
                'id', 'user_id', 'full_name', 'address', 'contact_number', 'email_address', 'facebook_name', 'plate_number',
                'make', 'model', 'year_model', 'product_to_purchase', 'appointment_date',
                'appointment_time', 'status', 'created_at',
            ])
            ->where('user_id', $userId)
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return array_map(fn ($row) => $this->mapDbRow((array) $row), $rows->all());
    }

    public function updateStatus(string $id, string $status): array
    {
        return $this->updateDetails($id, $status, null, null);
    }

    public function updateDetails(string $id, ?string $status = null, ?string $appointmentDate = null, ?string $appointmentTime = null, ?int $actorUserId = null): array
    {
        $status = $status === null ? null : trim($status);
        if ($status !== null) {
            $allowed = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
            if (! in_array($status, $allowed, true)) {
                throw new RuntimeException('Invalid inquiry status.', 422);
            }
        }

        if ($appointmentDate !== null && trim($appointmentDate) === '') {
            throw new RuntimeException('Appointment date is required.', 422);
        }

        if ($appointmentTime !== null && trim($appointmentTime) === '') {
            throw new RuntimeException('Appointment time is required.', 422);
        }

        if ($status === null && $appointmentDate === null && $appointmentTime === null) {
            throw new RuntimeException('No changes were provided.', 422);
        }

        $targetDate = $appointmentDate;
        $targetTime = $appointmentTime;

        if ($targetDate === null || $targetTime === null) {
            $existing = $this->getById($id);
            if ($existing === null) {
                throw new RuntimeException('Inquiry not found.', 404);
            }
            if ($targetDate === null) {
                $targetDate = (string) ($existing['appointmentDate'] ?? '');
            }
            if ($targetTime === null) {
                $targetTime = (string) ($existing['appointmentTime'] ?? '');
            }
        }

        $isScheduleChange = $appointmentDate !== null || $appointmentTime !== null;
        if ($isScheduleChange && $targetDate !== null && $targetTime !== null && trim((string) $targetDate) !== '' && trim((string) $targetTime) !== '') {
            $this->assertSlotCapacity((string) $targetDate, (string) $targetTime, $id);
        }

        $updates = ['updated_at' => Carbon::now()->toDateTimeString()];
        if ($status !== null) {
            $updates['status'] = $status;
        }
        if ($appointmentDate !== null) {
            $updates['appointment_date'] = $appointmentDate;
        }
        if ($appointmentTime !== null) {
            $updates['appointment_time'] = $appointmentTime;
        }

        DB::table('customer_inquiries')->where('id', $id)->update($updates);

        $inquiry = $this->getById($id);
        if ($inquiry === null) {
            throw new RuntimeException('Inquiry not found.', 404);
        }

        $this->syncOccupancyForInquiry($inquiry);

        if ($status !== null) {
            $this->activity->add($id, 'status_updated', "Status changed to {$status}", null, $actorUserId, $actorUserId ? 'admin' : 'system');
        }
        if ($isScheduleChange) {
            $this->activity->add($id, 'rescheduled', "Rescheduled to {$appointmentDate} at {$appointmentTime}", null, $actorUserId, $actorUserId ? 'admin' : 'system');
        }

        return $inquiry;
    }

    public function delete(string $id): void
    {
        $deleted = DB::table('customer_inquiries')->where('id', $id)->delete();

        if ($deleted === 0) {
            throw new RuntimeException('Inquiry not found.', 404);
        }

        $this->deleteOccupancyForInquiry($id);
    }

    public function getOccupiedSlots(string $date): array
    {
        $availability = $this->getAvailabilityForDate($date, []);

        return $availability['bookedSlots'];
    }

    public function getSlotCounts(string $date): array
    {
        $availability = $this->getAvailabilityForDate($date, []);

        return $availability['slotCounts'];
    }

    public function getAvailabilityForDate(string $date, array $allSlots = []): array
    {
        $slots = $allSlots;
        if ($slots === []) {
            $slots = $this->getAllSlotsForDate($date);
        }

        $activeAppointments = $this->getActiveAppointmentsForDate($date);
        $slotCounts = [];
        $bookedSlots = [];

        foreach ($slots as $slot) {
            $slotMinutes = $this->parseTimeToMinutes($slot);
            if ($slotMinutes === null) {
                continue;
            }

            $overlapCount = 0;
            foreach ($activeAppointments as $appointment) {
                if ($this->appointmentsOverlap($slotMinutes, $appointment['startMinutes'])) {
                    $overlapCount++;
                }
            }

            $slotCounts[$slot] = $overlapCount;
            if ($overlapCount >= self::SLOT_CAPACITY) {
                $bookedSlots[] = $slot;
            }
        }

        $availableSlots = array_values(array_diff($slots, $bookedSlots));

        return [
            'availableSlots' => $availableSlots,
            'bookedSlots' => $bookedSlots,
            'slotCounts' => $slotCounts,
            'slotCapacity' => self::SLOT_CAPACITY,
        ];
    }

    public function getStats(): array
    {
        $total = DB::table('customer_inquiries')->count();

        $byStatusRaw = DB::table('customer_inquiries')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->get();

        $byStatus = $byStatusRaw->pluck('cnt', 'status')->toArray();

        $pending = (int) ($byStatus['pending'] ?? 0);
        $confirmed = (int) ($byStatus['confirmed'] ?? 0);
        $inProgress = (int) ($byStatus['in_progress'] ?? 0);
        $completed = (int) ($byStatus['completed'] ?? 0);
        $cancelled = (int) ($byStatus['cancelled'] ?? 0);

        $thisWeek = DB::table('customer_inquiries')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $thisMonth = DB::table('customer_inquiries')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        $todayIso = Carbon::today()->toDateString();

        $todayInquiries = DB::table('customer_inquiries')
            ->where('appointment_date', $todayIso)
            ->count();

        $todayPending = DB::table('customer_inquiries')
            ->where('appointment_date', $todayIso)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->count();

        return [
            'totalInquiries' => $total,
            'pendingInquiries' => $pending,
            'confirmedInquiries' => $confirmed,
            'inProgressInquiries' => $inProgress,
            'completedInquiries' => $completed,
            'cancelledInquiries' => $cancelled,
            'activeInquiries' => $pending + $confirmed + $inProgress,
            'inquiriesThisWeek' => $thisWeek,
            'inquiriesThisMonth' => $thisMonth,
            'todayInquiries' => $todayInquiries,
            'todayPendingInquiries' => $todayPending,
        ];
    }

    public function getById(string $id): ?array
    {
        $row = DB::table('customer_inquiries')
            ->select([
                'id', 'user_id', 'full_name', 'address', 'contact_number', 'email_address', 'facebook_name', 'plate_number',
                'make', 'model', 'year_model', 'product_to_purchase', 'appointment_date',
                'appointment_time', 'status', 'created_at',
            ])
            ->where('id', $id)
            ->first();

        return $row ? $this->mapDbRow((array) $row) : null;
    }

    public function linkToUser(string $inquiryId, int $userId): void
    {
        $updated = DB::table('customer_inquiries')
            ->where('id', $inquiryId)
            ->update(['user_id' => $userId, 'updated_at' => Carbon::now()->toDateTimeString()]);

        if ($updated === 0) {
            throw new RuntimeException('Inquiry not found.', 404);
        }
    }

    private function normalizePayload(array $data): array
    {
        $getValue = static function (array $data, array $keys): string {
            foreach ($keys as $key) {
                if (array_key_exists($key, $data) && $data[$key] !== null) {
                    return trim((string) $data[$key]);
                }
            }

            return '';
        };

        return [
            'fullName' => $getValue($data, ['fullName', 'full_name', 'Full Name']),
            'address' => $getValue($data, ['address', 'Address']),
            'contactNumber' => $getValue($data, ['contactNumber', 'contact_number', 'Contact Number']),
            'emailAddress' => $getValue($data, ['emailAddress', 'email_address', 'Email address', 'Email Address']),
            'facebookName' => $getValue($data, ['facebookName', 'facebook_name', 'Facebook Name']),
            'plateNumber' => $getValue($data, ['plateNumber', 'plate_number', 'Plate Number']),
            'make' => $getValue($data, ['make', 'Car Make']),
            'model' => $getValue($data, ['model', 'Car Model']),
            'yearModel' => $getValue($data, ['yearModel', 'year_model', 'Year Model']),
            'productToPurchase' => $getValue($data, ['productToPurchase', 'product_to_purchase', 'Product to Purchase']),
            'appointmentDate' => $getValue($data, ['appointmentDate', 'appointment_date', 'Appointment Date', 'bookingDate', 'booking_date']),
            'appointmentTime' => $getValue($data, ['appointmentTime', 'appointment_time', 'Appointment Time', 'bookingTime', 'booking_time']),
        ];
    }

    private function validatePayload(array $inquiry): void
    {
        // Now handled mostly by CreateInquiryRequest, but leaving for internal safety
        $required = [
            'fullName' => 'Full name is required.',
            'address' => 'Address is required.',
            'contactNumber' => 'Contact number is required.',
            'emailAddress' => 'Email address is required.',
            'facebookName' => 'Facebook name is required.',
            'make' => 'Car make is required.',
            'model' => 'Car model is required.',
            'yearModel' => 'Year model is required.',
            'productToPurchase' => 'Product or service is required.',
            'appointmentDate' => 'Appointment date is required.',
            'appointmentTime' => 'Appointment time is required.',
        ];

        foreach ($required as $field => $message) {
            if (trim((string) ($inquiry[$field] ?? '')) === '') {
                throw new RuntimeException($message, 422);
            }
        }

        if (! filter_var($inquiry['emailAddress'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email address is required.', 422);
        }
    }

    private function mapDbRow(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'userId' => ! empty($row['user_id']) ? (string) $row['user_id'] : null,
            'fullName' => (string) ($row['full_name'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'contactNumber' => (string) ($row['contact_number'] ?? ''),
            'emailAddress' => (string) ($row['email_address'] ?? ''),
            'facebookName' => (string) ($row['facebook_name'] ?? ''),
            'plateNumber' => (string) ($row['plate_number'] ?? ''),
            'make' => (string) ($row['make'] ?? ''),
            'model' => (string) ($row['model'] ?? ''),
            'yearModel' => (string) ($row['year_model'] ?? ''),
            'productToPurchase' => (string) ($row['product_to_purchase'] ?? ''),
            'appointmentDate' => (string) ($row['appointment_date'] ?? ''),
            'appointmentTime' => (string) ($row['appointment_time'] ?? ''),
            'status' => (string) ($row['status'] ?? 'pending'),
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function syncOccupancyForInquiry(array $inquiry): void
    {
        $inquiryId = (string) ($inquiry['id'] ?? '');
        if ($inquiryId === '') {
            return;
        }

        $appointmentDate = trim((string) ($inquiry['appointmentDate'] ?? ''));
        $appointmentTime = trim((string) ($inquiry['appointmentTime'] ?? ''));
        $status = strtolower(trim((string) ($inquiry['status'] ?? 'pending')));

        if ($appointmentDate === '' || $appointmentTime === '' || $status === 'cancelled') {
            $this->deleteOccupancyForInquiry($inquiryId);

            return;
        }

        DB::table('inquiry_slot_occupancy')->updateOrInsert(
            ['inquiry_id' => $inquiryId],
            [
                'id' => (string) Str::uuid(),
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
                'status' => $status,
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]
        );
    }

    private function deleteOccupancyForInquiry(string $inquiryId): void
    {
        if ($inquiryId !== '') {
            DB::table('inquiry_slot_occupancy')->where('inquiry_id', $inquiryId)->delete();
        }
    }

    private function assertSlotCapacity(string $date, string $time, ?string $excludeInquiryId = null): void
    {
        if ($date === '' || $time === '') {
            return;
        }

        $count = $this->countOverlappingAppointments($date, $time, $excludeInquiryId);
        if ($count >= self::SLOT_CAPACITY) {
            throw new RuntimeException('This time slot is fully booked. Please choose a different time.', 409);
        }
    }

    private function getActiveAppointmentsForDate(string $date): array
    {
        $items = $this->getAll();
        $appointments = [];

        foreach ($items as $item) {
            $itemDate = trim((string) ($item['appointmentDate'] ?? ''));
            if ($itemDate !== $date) {
                continue;
            }

            $status = strtolower(trim((string) ($item['status'] ?? 'pending')));
            if ($status === 'cancelled' || $status === 'completed') {
                continue;
            }

            $itemTime = trim((string) ($item['appointmentTime'] ?? ''));
            $startMinutes = $this->parseTimeToMinutes($itemTime);
            if ($startMinutes === null) {
                continue;
            }

            $appointments[] = [
                'inquiryId' => (string) ($item['id'] ?? ''),
                'startMinutes' => $startMinutes,
            ];
        }

        return $appointments;
    }

    private function countOverlappingAppointments(string $date, string $time, ?string $excludeInquiryId = null): int
    {
        $candidateStart = $this->parseTimeToMinutes($time);
        if ($candidateStart === null) {
            return 0;
        }

        $appointments = $this->getActiveAppointmentsForDate($date);
        $count = 0;
        foreach ($appointments as $appointment) {
            if ($excludeInquiryId !== null && isset($appointment['inquiryId']) && (string) $appointment['inquiryId'] === $excludeInquiryId) {
                continue;
            }
            if ($this->appointmentsOverlap($candidateStart, $appointment['startMinutes'])) {
                $count++;
            }
        }

        return $count;
    }

    private function appointmentsOverlap(int $candidateStart, int $existingStart): bool
    {
        $candidateEnd = $candidateStart + self::SLOT_WINDOW_MINUTES;
        $existingEnd = $existingStart + self::SLOT_WINDOW_MINUTES;

        return $candidateStart < $existingEnd && $existingStart < $candidateEnd;
    }

    private function getAllSlotsForDate(string $date): array
    {
        $dayHours = $this->shopHoursService->getForDate($date);

        return $this->shopHoursService->generateSlots($dayHours);
    }

    private function parseTimeToMinutes(string $value): ?int
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?:\s*([ap]\.?m\.?))?$/i', $normalized, $matches) !== 1) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        $meridiem = isset($matches[3]) ? strtolower($matches[3]) : null;

        if ($meridiem === 'p' || $meridiem === 'pm') {
            if ($hours < 12) {
                $hours += 12;
            }
        } elseif ($meridiem === 'a' || $meridiem === 'am') {
            if ($hours === 12) {
                $hours = 0;
            }
        }

        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            return null;
        }

        return $hours * 60 + $minutes;
    }
}
