<?php

declare(strict_types=1);

namespace App\Services;

class TeamMemberService
{
    /** @return array<int, array<string, mixed>> */
    public function getAll(bool $activeOnly = true): array
    {
        throw new \RuntimeException('TeamMemberService::getAll() not implemented.', 501);
    }

    /** @return array<string, mixed>|null */
    public function findByUserId(int $userId): ?array
    {
        throw new \RuntimeException('TeamMemberService::findByUserId() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        throw new \RuntimeException('TeamMemberService::create() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        throw new \RuntimeException('TeamMemberService::update() not implemented.', 501);
    }

    public function delete(int $id): void
    {
        throw new \RuntimeException('TeamMemberService::delete() not implemented.', 501);
    }
}
