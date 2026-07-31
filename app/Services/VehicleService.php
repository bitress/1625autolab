<?php

declare(strict_types=1);

namespace App\Services;

class VehicleService
{
    /** @return array<int, array<string, mixed>> */
    public function getMakes(?int $year): array
    {
        throw new \RuntimeException('VehicleService::getMakes() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function getModels(string $make, ?int $year): array
    {
        throw new \RuntimeException('VehicleService::getModels() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function getTrims(string $make, string $model, int $limit, int $page): array
    {
        throw new \RuntimeException('VehicleService::getTrims() not implemented.', 501);
    }
}
