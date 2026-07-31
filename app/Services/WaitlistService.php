<?php

declare(strict_types=1);

namespace App\Services;

class WaitlistService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function join(array $data): array
    {
        throw new \RuntimeException('WaitlistService::join() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function getAll(string $status = ''): array
    {
        throw new \RuntimeException('WaitlistService::getAll() not implemented.', 501);
    }

    /** Admin or owner-user removal. $requestingUserId = null means admin bypass. */
    public function remove(int $id, ?int $requestingUserId): void
    {
        throw new \RuntimeException('WaitlistService::remove() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getClaimByToken(string $token): array
    {
        throw new \RuntimeException('WaitlistService::getClaimByToken() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function processAutoFill(): array
    {
        throw new \RuntimeException('WaitlistService::processAutoFill() not implemented.', 501);
    }
}
