<?php

declare(strict_types=1);

namespace App\Services;

class BeforeAfterService
{
    /** @return array<int, array<string, mixed>> */
    public function getAll(bool $includeInactive = false, string $make = '', string $model = ''): array
    {
        throw new \RuntimeException('BeforeAfterService::getAll() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getById(int $id, bool $requireActive = true): array
    {
        throw new \RuntimeException('BeforeAfterService::getById() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        throw new \RuntimeException('BeforeAfterService::create() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        throw new \RuntimeException('BeforeAfterService::update() not implemented.', 501);
    }

    public function delete(int $id): void
    {
        throw new \RuntimeException('BeforeAfterService::delete() not implemented.', 501);
    }
}
