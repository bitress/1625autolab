<?php

declare(strict_types=1);

namespace App\Services;

class FaqService
{
    /** @return array<int, array<string, mixed>> */
    public function getAll(bool $activeOnly = true): array
    {
        throw new \RuntimeException('FaqService::getAll() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        throw new \RuntimeException('FaqService::create() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        throw new \RuntimeException('FaqService::update() not implemented.', 501);
    }

    public function delete(int $id): void
    {
        throw new \RuntimeException('FaqService::delete() not implemented.', 501);
    }
}
