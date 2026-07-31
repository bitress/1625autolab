<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShopHoursService
{
    public const DAY_NAMES = [
        'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
    ];

    private const DEFAULTS = [
        ['dayOfWeek' => 0, 'isOpen' => false, 'openTime' => '09:00', 'closeTime' => '18:00', 'slotIntervalH' => 2],
        ['dayOfWeek' => 1, 'isOpen' => true,  'openTime' => '09:00', 'closeTime' => '18:00', 'slotIntervalH' => 2],
        ['dayOfWeek' => 2, 'isOpen' => true,  'openTime' => '09:00', 'closeTime' => '18:00', 'slotIntervalH' => 2],
        ['dayOfWeek' => 3, 'isOpen' => true,  'openTime' => '09:00', 'closeTime' => '18:00', 'slotIntervalH' => 2],
        ['dayOfWeek' => 4, 'isOpen' => true,  'openTime' => '09:00', 'closeTime' => '18:00', 'slotIntervalH' => 2],
        ['dayOfWeek' => 5, 'isOpen' => true,  'openTime' => '09:00', 'closeTime' => '18:00', 'slotIntervalH' => 2],
        ['dayOfWeek' => 6, 'isOpen' => true,  'openTime' => '09:00', 'closeTime' => '18:00', 'slotIntervalH' => 2],
    ];

    public function getAll(): array
    {
        $rows = DB::table('shop_hours')->orderBy('day_of_week', 'asc')->get()->toArray();

        if (empty($rows)) {
            return self::DEFAULTS;
        }

        return array_map([$this, 'mapRow'], $rows);
    }

    public function updateAll(array $hours): array
    {
        $this->validateHours($hours);

        $before = $this->getAll();

        DB::beginTransaction();
        try {
            foreach ($hours as $day) {
                DB::table('shop_hours')->updateOrInsert(
                    ['day_of_week' => $day['dayOfWeek']],
                    [
                        'is_open' => (int) $day['isOpen'],
                        'open_time' => $day['openTime'].':00',
                        'close_time' => $day['closeTime'].':00',
                        'slot_interval_h' => $day['slotIntervalH'],
                    ]
                );
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $updated = $this->getAll();

        $this->logShopHoursActivity('SHOP_HOURS_UPDATED', [
            'before' => $before,
            'after' => $updated,
        ]);

        return $updated;
    }

    public function getForDate(string $date): array
    {
        $ts = strtotime($date);
        if ($ts === false) {
            $dow = 0;
        } else {
            $dow = (int) date('w', $ts);
        }

        $closure = $this->getClosureForDate($date);
        if ($closure !== null) {
            return [
                'dayOfWeek' => $dow,
                'isOpen' => false,
                'openTime' => '09:00',
                'closeTime' => '18:00',
                'slotIntervalH' => 2,
                'closureReason' => $closure['reason'],
            ];
        }

        $all = $this->getAll();
        foreach ($all as $row) {
            if ($row['dayOfWeek'] === $dow) {
                return array_merge($row, ['closureReason' => null]);
            }
        }

        return [
            'dayOfWeek' => $dow,
            'isOpen' => false,
            'openTime' => '09:00',
            'closeTime' => '18:00',
            'slotIntervalH' => 2,
            'closureReason' => null,
        ];
    }

    public function getClosedDates(): array
    {
        $rows = DB::table('shop_closed_dates')
            ->where('closed_date', '>=', Carbon::now()->subDays(30)->toDateString())
            ->orderBy('closed_date', 'asc')
            ->get();

        return array_map(static fn ($r) => [
            'date' => $r->closed_date,
            'reason' => $r->reason,
            'isYearly' => (bool) $r->is_yearly,
        ], $rows->toArray());
    }

    public function addClosedDate(string $date, ?string $reason, bool $isYearly = false): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('Invalid date format. Expected YYYY-MM-DD.', 422);
        }

        DB::table('shop_closed_dates')->updateOrInsert(
            ['closed_date' => $date],
            [
                'reason' => $reason,
                'is_yearly' => (int) $isYearly,
            ]
        );

        $this->logShopHoursActivity('SHOP_CLOSED_DATE_ADDED', [
            'date' => $date,
            'reason' => $reason,
            'isYearly' => $isYearly,
        ]);
    }

    public function removeClosedDate(string $date): void
    {
        $before = $this->getClosureForDate($date);

        DB::table('shop_closed_dates')->where('closed_date', $date)->delete();

        if ($before !== null) {
            $this->logShopHoursActivity('SHOP_CLOSED_DATE_REMOVED', [
                'date' => $date,
                'before' => $before,
            ]);
        }
    }

    private function getClosureForDate(string $date): ?array
    {
        $row = DB::table('shop_closed_dates')->where('closed_date', $date)->first();

        if (! $row) {
            return null;
        }

        return [
            'date' => $row->closed_date,
            'reason' => $row->reason,
            'isYearly' => (bool) $row->is_yearly,
        ];
    }

    public function validateAppointmentSlot(string $date, string $time): void
    {
        $date = trim($date);
        if ($date === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('Invalid date format. Expected YYYY-MM-DD.', 422);
        }

        $ts = strtotime($date);
        if ($ts === false) {
            throw new RuntimeException('Invalid date. Expected a valid YYYY-MM-DD date.', 422);
        }

        $dayHours = $this->getForDate($date);
        if (! $dayHours['isOpen']) {
            throw new RuntimeException('The shop is closed on this date. Please choose a different day.', 422);
        }

        $candidateMinutes = $this->parseTimeToMinutes($time);
        if ($candidateMinutes === null) {
            throw new RuntimeException('Appointment time is required.', 422);
        }

        $allowedMinutes = [];
        foreach ($this->generateSlots($dayHours) as $slot) {
            $slotMinutes = $this->parseTimeToMinutes($slot);
            if ($slotMinutes !== null) {
                $allowedMinutes[] = $slotMinutes;
            }
        }

        if (! in_array($candidateMinutes, $allowedMinutes, true)) {
            throw new RuntimeException('Selected time is outside the shop schedule. Please choose a different time.', 422);
        }
    }

    public function parseTimeToMinutes(string $value): ?int
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', strtoupper($normalized)) ?? $normalized;
        if (preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(AM|PM)?$/', $normalized, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = isset($matches[2]) ? (int) $matches[2] : 0;
        if ($minute < 0 || $minute > 59 || $hour < 0 || $hour > 23) {
            return null;
        }

        $meridiem = $matches[3] ?? '';
        if ($meridiem === 'AM' && $hour === 12) {
            $hour = 0;
        } elseif ($meridiem === 'PM' && $hour !== 12) {
            $hour += 12;
        }

        return $hour * 60 + $minute;
    }

    public function generateSlots(array $dayHours): array
    {
        if (! $dayHours['isOpen']) {
            return [];
        }

        [$openH,  $openM] = array_map('intval', explode(':', $dayHours['openTime']));
        [$closeH, $closeM] = array_map('intval', explode(':', $dayHours['closeTime']));

        $openMinutes = $openH * 60 + $openM;
        $closeMinutes = $closeH * 60 + $closeM;

        if ($closeMinutes === 0) {
            $closeMinutes = 24 * 60;
        }

        $stepMinutes = $dayHours['slotIntervalH'] * 60;

        $slots = [];

        for ($m = $openMinutes; $m <= $closeMinutes; $m += $stepMinutes) {
            $display = $m % (24 * 60);

            $h = intdiv($display, 60);
            $min = $display % 60;

            $ampm = $h < 12 ? 'AM' : 'PM';
            $h12 = $h === 0 ? 12 : ($h > 12 ? $h - 12 : $h);

            $slots[] = sprintf('%02d:%02d %s', $h12, $min, $ampm);
        }

        return $slots;
    }

    private function mapRow($row): array
    {
        $rowArray = (array) $row;

        return [
            'dayOfWeek' => (int) $rowArray['day_of_week'],
            'isOpen' => (bool) $rowArray['is_open'],
            'openTime' => substr((string) $rowArray['open_time'], 0, 5),
            'closeTime' => substr((string) $rowArray['close_time'], 0, 5),
            'slotIntervalH' => (int) $rowArray['slot_interval_h'],
        ];
    }

    private function validateHours(array $hours): void
    {
        foreach ($hours as $day) {
            $dow = (int) ($day['dayOfWeek'] ?? -1);
            if ($dow < 0 || $dow > 6) {
                throw new RuntimeException("Invalid dayOfWeek: $dow", 422);
            }
            foreach (['openTime', 'closeTime'] as $field) {
                if (! preg_match('/^\d{2}:\d{2}$/', (string) ($day[$field] ?? ''))) {
                    throw new RuntimeException("Invalid $field format – expected HH:MM.", 422);
                }
            }
            $iv = (int) ($day['slotIntervalH'] ?? 0);
            if ($iv < 1 || $iv > 8) {
                throw new RuntimeException('slotIntervalH must be between 1 and 8.', 422);
            }
        }
    }

    private function logShopHoursActivity(string $event, array $properties = []): void
    {
        try {
            if (function_exists('activity')) {
                $logger = activity('shop')->performedOn(new User(['id' => 0])); // Fake model for shop subject

                $actorUserId = $this->resolveActorUserId();
                if ($actorUserId !== null && $actorUserId > 0) {
                    $logger->causedBy(new User(['id' => $actorUserId]));
                }

                if ($properties !== []) {
                    $logger->withProperties($properties);
                }

                $logger->log($event);
            }
        } catch (\Throwable $e) {
            error_log('[ShopHoursService] Activity logging failed: '.$e->getMessage());
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
}
