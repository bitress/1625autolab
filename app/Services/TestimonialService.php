<?php

declare(strict_types=1);

namespace App\Services;

class TestimonialService
{
    /** @return array<int, array<string, mixed>> */
    public function getAll(bool $activeOnly = true): array
    {
        throw new \RuntimeException('TestimonialService::getAll() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        throw new \RuntimeException('TestimonialService::create() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        throw new \RuntimeException('TestimonialService::update() not implemented.', 501);
    }

    public function delete(int $id): void
    {
        throw new \RuntimeException('TestimonialService::delete() not implemented.', 501);
    }
}
