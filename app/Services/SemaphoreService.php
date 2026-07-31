<?php

declare(strict_types=1);

namespace App\Services;

class SemaphoreService
{
    /** @return array<string, mixed> */
    public function getAccount(bool $refresh): array
    {
        throw new \RuntimeException('SemaphoreService::getAccount() not implemented.', 501);
    }

    /**
     * @param  array<string, string|int>  $filters
     * @return array<string, mixed>
     */
    public function getMessages(array $filters, bool $refresh): array
    {
        throw new \RuntimeException('SemaphoreService::getMessages() not implemented.', 501);
    }
}
