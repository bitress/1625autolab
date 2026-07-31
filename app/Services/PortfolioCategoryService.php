<?php

declare(strict_types=1);

namespace App\Services;

class PortfolioCategoryService
{
    /** @return array<int, array<string, mixed>> */
    public function getAll(): array
    {
        throw new \RuntimeException('PortfolioCategoryService::getAll() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getById(int $id): array
    {
        throw new \RuntimeException('PortfolioCategoryService::getById() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        throw new \RuntimeException('PortfolioCategoryService::create() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        throw new \RuntimeException('PortfolioCategoryService::update() not implemented.', 501);
    }

    public function delete(int $id): void
    {
        throw new \RuntimeException('PortfolioCategoryService::delete() not implemented.', 501);
    }
}
