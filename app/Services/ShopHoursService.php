<?php

declare(strict_types=1);

namespace App\Services;

class ShopHoursService
{
    /** @return array<int, array<string, mixed>> */
    public function getAll(): array
    {
        throw new \RuntimeException('ShopHoursService::getAll() not implemented.', 501);
    }

    /**
     * @param  array<int, array<string, mixed>>  $hours
     * @return array<int, array<string, mixed>>
     */
    public function updateAll(array $hours): array
    {
        throw new \RuntimeException('ShopHoursService::updateAll() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getForDate(string $date): array
    {
        throw new \RuntimeException('ShopHoursService::getForDate() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $dayHours
     * @return string[]
     */
    public function generateSlots(array $dayHours): array
    {
        throw new \RuntimeException('ShopHoursService::generateSlots() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function getClosedDates(): array
    {
        throw new \RuntimeException('ShopHoursService::getClosedDates() not implemented.', 501);
    }

    public function addClosedDate(string $date, ?string $reason, bool $isYearly): void
    {
        throw new \RuntimeException('ShopHoursService::addClosedDate() not implemented.', 501);
    }

    public function removeClosedDate(string $date): void
    {
        throw new \RuntimeException('ShopHoursService::removeClosedDate() not implemented.', 501);
    }
}
