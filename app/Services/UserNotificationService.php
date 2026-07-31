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

    private ?bool $hasUserIdColumnCache = null;

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
        if (! $this->hasUserIdColumn()) {
            $rows = DB::table('notifications')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();

            $rows = array_values(array_filter($rows, fn ($row) => $this->rowVisibleToViewer((array) $row, $adminMode, $userId)));

            return array_map([$this, 'formatRow'], $rows);
        }

        $query = DB::table('notifications');

        if ($adminMode) {
            $query->where(function ($q) use ($userId) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $userId);
            });
        } else {
            $query->where('user_id', $userId);
        }

        $rows = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();

        $rows = array_values(array_filter($rows, fn ($row) => $this->rowVisibleToViewer((array) $row, $adminMode, $userId)));

        return array_map([$this, 'formatRow'], $rows);
    }

    public function getUnreadCount(bool $adminMode, int $userId = 0): int
    {
        if (! $this->hasUserIdColumn()) {
            $rows = DB::table('notifications')->where('is_read', 0)->get()->toArray();

            return count(array_filter($rows, fn ($row) => $this->rowVisibleToViewer((array) $row, $adminMode, $userId)));
        }

        $query = DB::table('notifications')->where('is_read', 0);

        if ($adminMode) {
            $query->where(function ($q) use ($userId) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $userId);
            });
        } else {
            $query->where('user_id', $userId);
        }

        $rows = $query->get()->toArray();

        return count(array_filter($rows, fn ($row) => $this->rowVisibleToViewer((array) $row, $adminMode, $userId)));
    }

    public function markRead(int $id, bool $adminMode, int $userId = 0): void
    {
        $row = $this->findRowById($id);
        if ($row === null || ! $this->rowVisibleToViewer($row, $adminMode, $userId)) {
            return;
        }

        if (! $this->hasUserIdColumn()) {
            DB::table('notifications')->where('id', $id)->update(['is_read' => 1]);

            return;
        }

        $query = DB::table('notifications')->where('id', $id);

        if ($adminMode) {
            $query->where(function ($q) use ($userId) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $userId);
            });
        } else {
            $query->where('user_id', $userId);
        }

        $query->update(['is_read' => 1]);
    }

    public function markAllRead(bool $adminMode, int $userId = 0): void
    {
        if (! $this->hasUserIdColumn()) {
            $rows = DB::table('notifications')->where('is_read', 0)->get(['id', 'data'])->toArray();

            $ids = [];
            foreach ($rows as $row) {
                if ($this->rowVisibleToViewer((array) $row, $adminMode, $userId)) {
                    $ids[] = (int) $row->id;
                }
            }
            if (count($ids) > 0) {
                DB::table('notifications')->whereIn('id', $ids)->update(['is_read' => 1]);
            }

            return;
        }

        $query = DB::table('notifications')->where('is_read', 0);

        if ($adminMode) {
            $query->where(function ($q) use ($userId) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $userId);
            });
        } else {
            $query->where('user_id', $userId);
        }

        $rows = $query->get()->toArray();

        $ids = [];
        foreach ($rows as $row) {
            if ($this->rowVisibleToViewer((array) $row, $adminMode, $userId)) {
                $ids[] = (int) $row->id;
            }
        }

        if (count($ids) > 0) {
            DB::table('notifications')->whereIn('id', $ids)->update(['is_read' => 1]);
        }
    }

    public function delete(int $id, bool $adminMode, int $userId = 0): void
    {
        $row = $this->findRowById($id);
        if ($row === null || ! $this->rowVisibleToViewer($row, $adminMode, $userId)) {
            return;
        }

        if (! $this->hasUserIdColumn()) {
            DB::table('notifications')->where('id', $id)->delete();

            return;
        }

        $query = DB::table('notifications')->where('id', $id);

        if ($adminMode) {
            $query->where(function ($q) use ($userId) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $userId);
            });
        } else {
            $query->where('user_id', $userId);
        }

        $query->delete();
    }

    private function insert(?int $userId, string $type, string $title, string $message, ?array $data): void
    {
        $now = Carbon::now();

        if ($this->hasUserIdColumn()) {
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data !== null ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
                'is_read' => 0,
                'created_at' => $now,
            ]);

            return;
        }

        // Legacy schema fallback: embed target user in payload when user_id column is unavailable.
        if ($userId !== null) {
            $payload = is_array($data) ? $data : [];
            $payload['_targetUserId'] = $userId;
            $data = $payload;
        }

        DB::table('notifications')->insert([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data !== null ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
            'is_read' => 0,
            'created_at' => $now,
        ]);
    }

    private function formatRow(array $row): array
    {
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

    private function hasUserIdColumn(): bool
    {
        if ($this->hasUserIdColumnCache !== null) {
            return $this->hasUserIdColumnCache;
        }

        try {
            DB::table('notifications')->select('user_id')->limit(1)->get();
            $this->hasUserIdColumnCache = true;
        } catch (\Throwable) {
            $this->hasUserIdColumnCache = false;
        }

        return $this->hasUserIdColumnCache;
    }

    private function rowVisibleToViewer(array $row, bool $adminMode, int $userId): bool
    {
        $explicitUserId = array_key_exists('user_id', $row) && $row['user_id'] !== null
            ? (int) $row['user_id']
            : null;

        if ($explicitUserId !== null) {
            return $userId > 0 && $explicitUserId === $userId;
        }

        $legacyTargetUserId = $this->extractLegacyTargetUserId($row);
        if ($legacyTargetUserId !== null) {
            return $userId > 0 && $legacyTargetUserId === $userId;
        }

        $type = strtolower(trim((string) ($row['type'] ?? '')));

        return $adminMode && in_array($type, self::ADMIN_BROADCAST_TYPES, true);
    }

    private function extractLegacyTargetUserId(array $row): ?int
    {
        if (empty($row['data'])) {
            return null;
        }

        $decoded = json_decode((string) $row['data'], true);
        if (! is_array($decoded) || ! isset($decoded['_targetUserId'])) {
            return null;
        }

        $target = (int) $decoded['_targetUserId'];

        return $target > 0 ? $target : null;
    }

    private function findRowById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = DB::table('notifications')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }
}
