<?php

declare(strict_types=1);

namespace App\Services;

class NotificationPreferencesService
{
    /** @return array<string, mixed>|null */
    public function getForUser(int $userId): ?array
    {
        throw new \RuntimeException('NotificationPreferencesService::getForUser() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function save(int $userId, array $data): array
    {
        throw new \RuntimeException('NotificationPreferencesService::save() not implemented.', 501);
    }
}
