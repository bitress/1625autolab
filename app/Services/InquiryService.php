<?php

declare(strict_types=1);

namespace App\Services;

class InquiryService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        throw new \RuntimeException('InquiryService::create() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function getAll(): array
    {
        throw new \RuntimeException('InquiryService::getAll() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function getAllForUser(mixed $userId): array
    {
        throw new \RuntimeException('InquiryService::getAllForUser() not implemented.', 501);
    }

    /** @return array<string, mixed>|null */
    public function getById(string $id): ?array
    {
        throw new \RuntimeException('InquiryService::getById() not implemented.', 501);
    }

    /**
     * @param  string[]  $allSlots
     * @return array<string, mixed>
     */
    public function getAvailabilityForDate(string $date, array $allSlots): array
    {
        throw new \RuntimeException('InquiryService::getAvailabilityForDate() not implemented.', 501);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateDetails(
        string $id,
        ?string $status,
        ?string $appointmentDate,
        ?string $appointmentTime,
        ?int $actorId
    ): array {
        throw new \RuntimeException('InquiryService::updateDetails() not implemented.', 501);
    }

    public function delete(string $id): void
    {
        throw new \RuntimeException('InquiryService::delete() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getStats(): array
    {
        throw new \RuntimeException('InquiryService::getStats() not implemented.', 501);
    }
}
