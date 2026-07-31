<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Persistent booking activity timeline entries.
 */
class BookingActivityService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForBooking(string $bookingId): array
    {
        $rows = DB::table('booking_activity_logs')
            ->where('booking_id', $bookingId)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return $rows->map(fn ($row) => $this->formatRow((array) $row))->toArray();
    }

    public function add(
        string $bookingId,
        string $eventType,
        string $action,
        ?string $detail = null,
        ?int $actorUserId = null,
        string $actorRole = 'system',
        ?string $createdAt = null
    ): void {
        if (! in_array($actorRole, ['system', 'admin', 'client'], true)) {
            $actorRole = 'system';
        }

        if ($createdAt !== null) {
            $ts = strtotime($createdAt);
            $createdAt = $ts === false ? Carbon::now()->toDateTimeString() : date('Y-m-d H:i:s', $ts);
        } else {
            $createdAt = Carbon::now()->toDateTimeString();
        }

        DB::table('booking_activity_logs')->insert([
            'booking_id' => $bookingId,
            'actor_user_id' => $actorUserId,
            'actor_role' => $actorRole,
            'event_type' => $eventType,
            'action' => $action,
            'detail' => $detail,
            'created_at' => $createdAt,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function formatRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'bookingId' => (string) $row['booking_id'],
            'actorUserId' => $row['actor_user_id'] !== null ? (int) $row['actor_user_id'] : null,
            'actorRole' => (string) $row['actor_role'],
            'eventType' => (string) $row['event_type'],
            'action' => (string) $row['action'],
            'detail' => $row['detail'] !== null ? (string) $row['detail'] : null,
            'createdAt' => (string) $row['created_at'],
        ];
    }
}
