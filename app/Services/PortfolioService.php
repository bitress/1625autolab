<?php

declare(strict_types=1);

namespace App\Services;

class PortfolioService
{
    /** @return array<int, array<string, mixed>> */
    public function getAll(bool $includeInactive = false): array
    {
        throw new \RuntimeException('PortfolioService::getAll() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getById(int $id, bool $requireActive = true): array
    {
        throw new \RuntimeException('PortfolioService::getById() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getBySlug(string $slug): array
    {
        throw new \RuntimeException('PortfolioService::getBySlug() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        throw new \RuntimeException('PortfolioService::create() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        throw new \RuntimeException('PortfolioService::update() not implemented.', 501);
    }

    public function delete(int $id): void
    {
        throw new \RuntimeException('PortfolioService::delete() not implemented.', 501);
    }
}
