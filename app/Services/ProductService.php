<?php

declare(strict_types=1);

namespace App\Services;

class ProductService
{
    /** @return array<int, array<string, mixed>> */
    public function getAll(bool $includeInactive = false): array
    {
        throw new \RuntimeException('ProductService::getAll() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getByIdentifier(string $id, bool $requireActive = true): array
    {
        throw new \RuntimeException('ProductService::getByIdentifier() not implemented.', 501);
    }

    /** Resolve a slug or numeric id to the internal integer PK. */
    public function resolveId(string $id): int
    {
        throw new \RuntimeException('ProductService::resolveId() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        throw new \RuntimeException('ProductService::create() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        throw new \RuntimeException('ProductService::update() not implemented.', 501);
    }

    public function delete(int $id): void
    {
        throw new \RuntimeException('ProductService::delete() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createVariation(int $productId, array $data): array
    {
        throw new \RuntimeException('ProductService::createVariation() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateVariation(int $productId, int $variationId, array $data): array
    {
        throw new \RuntimeException('ProductService::updateVariation() not implemented.', 501);
    }

    public function deleteVariation(int $productId, int $variationId): void
    {
        throw new \RuntimeException('ProductService::deleteVariation() not implemented.', 501);
    }
}
