<?php

declare(strict_types=1);

namespace App\Services;

class UserNotificationService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForViewer(bool $isAdmin, int $userId): array
    {
        throw new \RuntimeException('UserNotificationService::getForViewer() not implemented.', 501);
    }

    public function getUnreadCount(bool $isAdmin, int $userId): int
    {
        throw new \RuntimeException('UserNotificationService::getUnreadCount() not implemented.', 501);
    }

    public function markRead(int $id, bool $isAdmin, int $userId): void
    {
        throw new \RuntimeException('UserNotificationService::markRead() not implemented.', 501);
    }

    public function markAllRead(bool $isAdmin, int $userId): void
    {
        throw new \RuntimeException('UserNotificationService::markAllRead() not implemented.', 501);
    }

    public function delete(int $id, bool $isAdmin, int $userId): void
    {
        throw new \RuntimeException('UserNotificationService::delete() not implemented.', 501);
    }
}
