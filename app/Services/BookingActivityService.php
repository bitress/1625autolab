<?php

declare(strict_types=1);

namespace App\Services;

class BookingActivityService
{
    /** @return array<int, array<string, mixed>> */
    public function getForBooking(string $bookingId): array
    {
        throw new \RuntimeException('BookingActivityService::getForBooking() not implemented.', 501);
    }

    public function add(
        string $bookingId,
        string $eventType,
        string $action,
        ?string $detail,
        ?int $actorId,
        string $actorRole
    ): void {
        throw new \RuntimeException('BookingActivityService::add() not implemented.', 501);
    }
}
