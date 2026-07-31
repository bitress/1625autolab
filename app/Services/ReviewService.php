<?php

declare(strict_types=1);

namespace App\Services;

class ReviewService
{
    /** @return array<int, array<string, mixed>> */
    public function getPublished(?int $serviceId): array
    {
        throw new \RuntimeException('ReviewService::getPublished() not implemented.', 501);
    }

    /** @return array<string, mixed>|null */
    public function getForBooking(string $bookingId): ?array
    {
        throw new \RuntimeException('ReviewService::getForBooking() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(string $bookingId, int $userId, array $data): array
    {
        throw new \RuntimeException('ReviewService::create() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function getAll(): array
    {
        throw new \RuntimeException('ReviewService::getAll() not implemented.', 501);
    }

    public function approve(int $id): void
    {
        throw new \RuntimeException('ReviewService::approve() not implemented.', 501);
    }

    public function reject(int $id): void
    {
        throw new \RuntimeException('ReviewService::reject() not implemented.', 501);
    }

    public function delete(int $id): void
    {
        throw new \RuntimeException('ReviewService::delete() not implemented.', 501);
    }
}
