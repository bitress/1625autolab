<?php

declare(strict_types=1);

namespace App\Services;

class VehicleCrudService
{
    /** @return array<int, array<string, mixed>> */
    public function getByUserId(int $userId): array
    {
        throw new \RuntimeException('VehicleCrudService::getByUserId() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(int $userId, array $data): array
    {
        throw new \RuntimeException('VehicleCrudService::create() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, int $userId, array $data): array
    {
        throw new \RuntimeException('VehicleCrudService::update() not implemented.', 501);
    }

    public function delete(int $id, int $userId): void
    {
        throw new \RuntimeException('VehicleCrudService::delete() not implemented.', 501);
    }
}
