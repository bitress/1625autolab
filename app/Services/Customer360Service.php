<?php

declare(strict_types=1);

namespace App\Services;

class Customer360Service
{
    /** @return array<string, mixed> */
    public function getByUserId(int $userId, int $limit): array
    {
        throw new \RuntimeException('Customer360Service::getByUserId() not implemented.', 501);
    }
}
