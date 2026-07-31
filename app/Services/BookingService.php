<?php

declare(strict_types=1);

namespace App\Services;

/**
 * BookingService stub.
 * All method signatures mirror Router.php call-sites exactly.
 */
class BookingService
{
    /** @return array<int, array<string, mixed>> */
    public function getAll(): array
    {
        throw new \RuntimeException('BookingService::getAll() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, ?int $userId): array
    {
        throw new \RuntimeException('BookingService::create() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getById(string $id, int $userId): array
    {
        throw new \RuntimeException('BookingService::getById() not implemented.', 501);
    }

    /** @return array<string, mixed>|null */
    public function adminFindById(string $id): ?array
    {
        throw new \RuntimeException('BookingService::adminFindById() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function getByUserId(int $userId): array
    {
        throw new \RuntimeException('BookingService::getByUserId() not implemented.', 501);
    }

    /** @return string[] */
    public function getBookedSlots(string $date): array
    {
        throw new \RuntimeException('BookingService::getBookedSlots() not implemented.', 501);
    }

    /** @return array<string, int> */
    public function getSlotCounts(string $date): array
    {
        throw new \RuntimeException('BookingService::getSlotCounts() not implemented.', 501);
    }

    public function getSlotCapacity(): int
    {
        throw new \RuntimeException('BookingService::getSlotCapacity() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function updateStatus(string $id, string $status, ?int $actorId, string $actorRole): array
    {
        throw new \RuntimeException('BookingService::updateStatus() not implemented.', 501);
    }

    public function delete(string $id): void
    {
        throw new \RuntimeException('BookingService::delete() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function assignTechnician(string $id, ?int $techId, ?int $actorId, string $actorRole): array
    {
        throw new \RuntimeException('BookingService::assignTechnician() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function reschedule(string $id, int $userId, string $date, string $time): array
    {
        throw new \RuntimeException('BookingService::reschedule() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function adminReschedule(string $id, string $date, string $time, ?int $actorId, string $actorRole): array
    {
        throw new \RuntimeException('BookingService::adminReschedule() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function cancelByUser(string $id, int $userId): array
    {
        throw new \RuntimeException('BookingService::cancelByUser() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function updatePartsStatus(string $id, bool $waiting, string $partsNotes, ?int $actorId, string $actorRole): array
    {
        throw new \RuntimeException('BookingService::updatePartsStatus() not implemented.', 501);
    }

    /**
     * @param  string[]  $photoUrls
     * @return array<string, mixed>
     */
    public function updateQaPhotos(string $id, string $stage, array $photoUrls, ?int $actorId): array
    {
        throw new \RuntimeException('BookingService::updateQaPhotos() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function updateInternalNotes(string $id, string $notes, ?int $actorId, string $actorRole): array
    {
        throw new \RuntimeException('BookingService::updateInternalNotes() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateCalibrationData(string $id, array $data, ?int $actorId, string $actorRole): array
    {
        throw new \RuntimeException('BookingService::updateCalibrationData() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getStats(): array
    {
        throw new \RuntimeException('BookingService::getStats() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getCustomerStats(int $userId): array
    {
        throw new \RuntimeException('BookingService::getCustomerStats() not implemented.', 501);
    }
}
