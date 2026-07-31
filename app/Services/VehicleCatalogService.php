<?php

declare(strict_types=1);

namespace App\Services;

class VehicleCatalogService
{
    /** @return array<int, array<string, mixed>> */
    public function listMakes(): array
    {
        throw new \RuntimeException('VehicleCatalogService::listMakes() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function listModelsByMakeName(string $make): array
    {
        throw new \RuntimeException('VehicleCatalogService::listModelsByMakeName() not implemented.', 501);
    }

    public function createMake(string $name): int
    {
        throw new \RuntimeException('VehicleCatalogService::createMake() not implemented.', 501);
    }

    public function updateMake(int $id, string $name): void
    {
        throw new \RuntimeException('VehicleCatalogService::updateMake() not implemented.', 501);
    }

    public function deleteMake(int $id): void
    {
        throw new \RuntimeException('VehicleCatalogService::deleteMake() not implemented.', 501);
    }

    public function createModel(int $makeId, string $name): int
    {
        throw new \RuntimeException('VehicleCatalogService::createModel() not implemented.', 501);
    }

    public function updateModel(int $id, int $makeId, string $name): void
    {
        throw new \RuntimeException('VehicleCatalogService::updateModel() not implemented.', 501);
    }

    public function deleteModel(int $id): void
    {
        throw new \RuntimeException('VehicleCatalogService::deleteModel() not implemented.', 501);
    }
}
