<?php

declare(strict_types=1);

namespace App\Services;

class BlogPostService
{
    /** @return array<int, array<string, mixed>> */
    public function getAll(bool $publishedOnly = true): array
    {
        throw new \RuntimeException('BlogPostService::getAll() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getById(int $id, bool $publishedOnly = true): array
    {
        throw new \RuntimeException('BlogPostService::getById() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        throw new \RuntimeException('BlogPostService::create() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        throw new \RuntimeException('BlogPostService::update() not implemented.', 501);
    }

    public function delete(int $id): void
    {
        throw new \RuntimeException('BlogPostService::delete() not implemented.', 501);
    }
}
