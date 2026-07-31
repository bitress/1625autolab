<?php

declare(strict_types=1);

namespace App\Services;

class BuildUpdateService
{
    /** @return array<int, array<string, mixed>> */
    public function getByBookingId(string $bookingId): array
    {
        throw new \RuntimeException('BuildUpdateService::getByBookingId() not implemented.', 501);
    }

    /**
     * @param  string[]  $photoUrls
     * @return array<string, mixed>
     */
    public function create(string $bookingId, string $note, array $photoUrls): array
    {
        throw new \RuntimeException('BuildUpdateService::create() not implemented.', 501);
    }
}
