<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserNotificationService
{
    /** @var string[] */
    private const ADMIN_BROADCAST_TYPES = [
        'new_booking',
        'new_order',
        'security_alert',
    ];

    public function createForAdmin(
        string $type,
        string $title,
        string $message,
        ?array $data = null
    ): void {
        $this->insert(null, $type, $title, $message, $data);
    }

    public function createForUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?array $data = null
    ): void {
        $this->insert($userId, $type, $title, $message, $data);
    }

    public function getForViewer(bool $adminMode, int $userId = 0, int $limit = 50): array
    {
        $query = DB::table('notifications');

        if ($adminMode) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('user_id')
                            ->whereIn('type', self::ADMIN_BROADCAST_TYPES);
                    });
            });
        } else {
            $query->where('user_id', $userId);
        }

        $rows = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();

        return array_map([$this, 'formatRow'], $rows);
    }

    public function getUnreadCount(bool $adminMode, int $userId = 0): int
    {
        $query = DB::table('notifications')->where('is_read', 0);

        if ($adminMode) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('user_id')
                            ->whereIn('type', self::ADMIN_BROADCAST_TYPES);
                    });
            });
        } else {
            $query->where('user_id', $userId);
        }

        return $query->count();
    }

    public function markRead(int $id, bool $adminMode, int $userId = 0): void
    {
        $query = DB::table('notifications')->where('id', $id);

        if ($adminMode) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('user_id')
                            ->whereIn('type', self::ADMIN_BROADCAST_TYPES);
                    });
            });
        } else {
            $query->where('user_id', $userId);
        }

        $query->update(['is_read' => 1]);
    }

    public function markAllRead(bool $adminMode, int $userId = 0): void
    {
        $query = DB::table('notifications')->where('is_read', 0);

        if ($adminMode) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('user_id')
                            ->whereIn('type', self::ADMIN_BROADCAST_TYPES);
                    });
            });
        } else {
            $query->where('user_id', $userId);
        }

        $query->update(['is_read' => 1]);
    }

    public function delete(int $id, bool $adminMode, int $userId = 0): void
    {
        $query = DB::table('notifications')->where('id', $id);

        if ($adminMode) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('user_id')
                            ->whereIn('type', self::ADMIN_BROADCAST_TYPES);
                    });
            });
        } else {
            $query->where('user_id', $userId);
        }

        $query->delete();
    }

    private function insert(?int $userId, string $type, string $title, string $message, ?array $data): void
    {
        DB::table('notifications')->insert([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data !== null ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
            'is_read' => 0,
            'created_at' => Carbon::now(),
        ]);
    }

    private function formatRow(array|object $row): array
    {
        $row = (array) $row;
        $data = null;
        if (! empty($row['data'])) {
            $decoded = json_decode((string) $row['data'], true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        return [
            'id' => (int) $row['id'],
            'userId' => array_key_exists('user_id', $row) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            'type' => (string) $row['type'],
            'title' => (string) $row['title'],
            'message' => (string) $row['message'],
            'data' => $data,
            'isRead' => (bool) ($row['is_read'] ?? false),
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
