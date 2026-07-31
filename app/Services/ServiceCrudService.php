<?php

declare(strict_types=1);

namespace App\Services;

class ServiceCrudService
{
    /** @return array<int, array<string, mixed>> */
    public function getAll(bool $includeInactive = false): array
    {
        throw new \RuntimeException('ServiceCrudService::getAll() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getById(int $id, bool $requireActive = true): array
    {
        throw new \RuntimeException('ServiceCrudService::getById() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getBySlug(string $slug, bool $requireActive = true): array
    {
        throw new \RuntimeException('ServiceCrudService::getBySlug() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        throw new \RuntimeException('ServiceCrudService::create() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        throw new \RuntimeException('ServiceCrudService::update() not implemented.', 501);
    }

    public function delete(int $id): void
    {
        throw new \RuntimeException('ServiceCrudService::delete() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createVariation(int $serviceId, array $data): array
    {
        throw new \RuntimeException('ServiceCrudService::createVariation() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateVariation(int $serviceId, int $variationId, array $data): array
    {
        throw new \RuntimeException('ServiceCrudService::updateVariation() not implemented.', 501);
    }

    public function deleteVariation(int $serviceId, int $variationId): void
    {
        throw new \RuntimeException('ServiceCrudService::deleteVariation() not implemented.', 501);
    }
}
