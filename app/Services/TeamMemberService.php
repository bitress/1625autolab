<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * TeamMemberService
 *
 * Full CRUD for the team_members table.
 */
class TeamMemberService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function getAll(bool $activeOnly = false): array
    {
        $query = DB::table('team_members as tm')
            ->leftJoin('users as u', 'u.id', '=', 'tm.user_id')
            ->select(
                'tm.id',
                'tm.user_id',
                DB::raw("COALESCE(NULLIF(TRIM(u.name), ''), tm.name) AS name"),
                DB::raw("COALESCE(NULLIF(TRIM(u.role), ''), tm.role) AS role"),
                'tm.image_url',
                'tm.bio',
                'tm.full_bio',
                DB::raw("COALESCE(NULLIF(TRIM(u.email), ''), tm.email) AS email"),
                DB::raw("COALESCE(NULLIF(TRIM(u.phone), ''), tm.phone) AS phone"),
                'tm.facebook',
                'tm.instagram',
                'tm.sort_order',
                'tm.is_active',
                'tm.created_at',
                'tm.updated_at'
            )
            ->whereRaw("LOWER(TRIM(COALESCE(tm.role, ''))) <> 'client'")
            ->whereRaw("(u.role IS NULL OR LOWER(TRIM(u.role)) <> 'client')");

        if ($activeOnly) {
            $query->where('tm.is_active', 1);
        }

        $query->orderBy('tm.sort_order', 'asc')->orderBy('tm.id', 'asc');

        $rows = $query->get();

        return $rows->map(fn ($row) => $this->mapRow((array) $row))->toArray();
    }

    /** @return array<string, mixed> */
    public function getById(int $id): array
    {
        $row = DB::table('team_members as tm')
            ->leftJoin('users as u', 'u.id', '=', 'tm.user_id')
            ->select(
                'tm.id',
                'tm.user_id',
                DB::raw("COALESCE(NULLIF(TRIM(u.name), ''), tm.name) AS name"),
                DB::raw("COALESCE(NULLIF(TRIM(u.role), ''), tm.role) AS role"),
                'tm.image_url',
                'tm.bio',
                'tm.full_bio',
                DB::raw("COALESCE(NULLIF(TRIM(u.email), ''), tm.email) AS email"),
                DB::raw("COALESCE(NULLIF(TRIM(u.phone), ''), tm.phone) AS phone"),
                'tm.facebook',
                'tm.instagram',
                'tm.sort_order',
                'tm.is_active',
                'tm.created_at',
                'tm.updated_at'
            )
            ->where('tm.id', $id)
            ->whereRaw("LOWER(TRIM(COALESCE(tm.role, ''))) <> 'client'")
            ->whereRaw("(u.role IS NULL OR LOWER(TRIM(u.role)) <> 'client')")
            ->first();

        if (! $row) {
            throw new RuntimeException('Team member not found.', 404);
        }

        return $this->mapRow((array) $row);
    }

    /** @return array<string, mixed> */
    public function create(array $data): array
    {
        $this->validatePayload($data);

        $payload = $this->withUserIdentity($data);
        $params = $this->bindParams($payload);
        $params['created_at'] = Carbon::now();
        $params['updated_at'] = Carbon::now();

        $id = DB::table('team_members')->insertGetId($params);

        $created = $this->getById($id);

        $this->logTeamMemberActivity('TEAM_MEMBER_CREATED', $created, [
            'after' => $created,
        ]);

        return $created;
    }

    /** @return array<string, mixed> */
    public function update(int $id, array $data): array
    {
        $before = $this->getById($id);

        $merged = $this->withUserIdentity(array_merge($before, $data));
        $this->validatePayload($merged);

        $params = $this->bindParams($merged);
        $params['updated_at'] = Carbon::now();

        DB::table('team_members')->where('id', $id)->update($params);

        $oldImage = (string) ($before['imageUrl'] ?? '');
        $newImage = (string) ($merged['imageUrl'] ?? ($merged['image_url'] ?? ''));
        if ($oldImage !== '' && $oldImage !== $newImage) {
            $this->deleteManagedImageUrl($oldImage);
        }

        $updated = $this->getById($id);

        $this->logTeamMemberActivity('TEAM_MEMBER_UPDATED', $updated, [
            'before' => $before,
            'after' => $updated,
        ]);

        return $updated;
    }

    public function delete(int $id): void
    {
        $before = $this->getById($id);

        $affected = DB::table('team_members')->where('id', $id)->delete();
        if ($affected === 0) {
            throw new RuntimeException('Team member not found.', 404);
        }

        $oldImage = (string) ($before['imageUrl'] ?? '');
        if ($oldImage !== '') {
            $this->deleteManagedImageUrl($oldImage);
        }

        $this->logTeamMemberActivity('TEAM_MEMBER_DELETED', $before, [
            'before' => $before,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findByUserId(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $row = DB::table('team_members')->where('user_id', $userId)->first();
        if ($row) {
            return $this->mapRow((array) $row);
        }

        // Legacy fallback: attempt to link an existing row by normalized identity
        $user = $this->getUserIdentity($userId);
        $legacy = $this->findUnlinkedByNormalizedIdentity($user);
        if ($legacy) {
            DB::table('team_members')
                ->where('id', (int) ($legacy['id'] ?? 0))
                ->update(['user_id' => $userId]);

            return $this->getById((int) ($legacy['id'] ?? 0));
        }

        return null;
    }

    /** @param array<string, mixed> $user @return array<string, mixed>|null */
    private function findUnlinkedByNormalizedIdentity(array $user): ?array
    {
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $phone = $this->normalizePhoneForMatch((string) ($user['phone'] ?? ''));

        if ($email !== '') {
            $row = DB::table('team_members')
                ->whereNull('user_id')
                ->whereNotNull('email')
                ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                ->first();

            if ($row) {
                return (array) $row;
            }
        }

        if ($phone !== '') {
            $rows = DB::table('team_members')
                ->whereNull('user_id')
                ->whereNotNull('phone')
                ->where('phone', '<>', '')
                ->get();

            foreach ($rows as $row) {
                $rowArray = (array) $row;
                $candidate = $this->normalizePhoneForMatch((string) ($rowArray['phone'] ?? ''));
                if ($candidate !== '' && $candidate === $phone) {
                    return $rowArray;
                }
            }
        }

        return null;
    }

    private function normalizePhoneForMatch(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return '';
        }

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0'.$digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return $digits;
        }

        return $digits;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'userId' => isset($row['user_id']) ? (int) $row['user_id'] : null,
            'name' => $row['name'],
            'role' => $row['role'] ?? '',
            'imageUrl' => $row['image_url'] ?? null,
            'bio' => $row['bio'] ?? null,
            'fullBio' => $row['full_bio'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'facebook' => $row['facebook'] ?? null,
            'instagram' => $row['instagram'] ?? null,
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'isActive' => (bool) ($row['is_active'] ?? true),
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    /** @return array<string, mixed> */
    private function bindParams(array $data): array
    {
        return [
            'user_id' => isset($data['userId']) ? (int) $data['userId'] : ($data['user_id'] ?? null),
            'name' => $data['name'] ?? '',
            'role' => $data['role'] ?? '',
            'image_url' => $data['imageUrl'] ?? ($data['image_url'] ?? null),
            'bio' => $data['bio'] ?? null,
            'full_bio' => $data['fullBio'] ?? ($data['full_bio'] ?? null),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'facebook' => $data['facebook'] ?? null,
            'instagram' => $data['instagram'] ?? null,
            'sort_order' => (int) ($data['sortOrder'] ?? $data['sort_order'] ?? 0),
            'is_active' => (int) (isset($data['isActive']) ? (bool) $data['isActive'] : true),
        ];
    }

    private function validatePayload(array $data): void
    {
        $role = strtolower(trim((string) ($data['role'] ?? '')));
        if ($role === 'client') {
            throw new RuntimeException('Client accounts cannot be saved as team members.', 422);
        }

        $hasUser = (int) ($data['userId'] ?? 0) > 0 || (int) ($data['user_id'] ?? 0) > 0;
        if (! $hasUser) {
            throw new RuntimeException('Team member must be linked to an existing user.', 422);
        }
    }

    /** @return array<string, mixed> */
    private function withUserIdentity(array $data): array
    {
        $userId = (int) ($data['userId'] ?? ($data['user_id'] ?? 0));
        if ($userId <= 0) {
            return $data;
        }

        $user = $this->getUserIdentity($userId);

        $data['userId'] = (int) $user['id'];
        $data['name'] = (string) ($user['name'] ?? '');
        $data['email'] = (string) ($user['email'] ?? '');
        $data['phone'] = (string) ($user['phone'] ?? '');
        $data['role'] = (string) ($user['role'] ?? ($data['role'] ?? ''));

        return $data;
    }

    /** @return array<string, mixed> */
    private function getUserIdentity(int $userId): array
    {
        if ($userId <= 0) {
            throw new RuntimeException('Selected user was not found.', 422);
        }

        $user = DB::table('users')->where('id', $userId)->first(['id', 'name', 'email', 'phone', 'role']);
        if (! $user) {
            throw new RuntimeException('Selected user was not found.', 422);
        }
        $userArray = (array) $user;
        if (strtolower(trim((string) ($userArray['role'] ?? ''))) === 'client') {
            throw new RuntimeException('Client accounts cannot be assigned as team members.', 422);
        }

        return $userArray;
    }

    private function deleteManagedImageUrl(string $url): void
    {
        try {
            if (class_exists(UploadStorage::class)) {
                (new UploadStorage)->deleteByUrl($url);
            }
        } catch (Throwable) {
            // Keep CRUD successful even if storage cleanup fails.
        }
    }

    /** @param array<string, mixed> $entity @param array<string, mixed> $properties */
    private function logTeamMemberActivity(string $description, array $entity, array $properties = []): void
    {
        try {
            if (! function_exists('activity')) {
                return;
            }

            $subjectId = (string) ((int) ($entity['id'] ?? 0));
            $logger = activity()
                ->forSubject('team_members', $subjectId)
                ->withProperties($properties);

            $actorUserId = $this->resolveActorUserId();
            if ($actorUserId !== null) {
                $logger->byUser($actorUserId);
            }

            $logger->log($description, 'team_members');
        } catch (Throwable $e) {
            error_log('[TeamMemberService] Failed to write activity log: '.$e->getMessage());
        }
    }

    private function resolveActorUserId(): ?int
    {
        try {
            $payload = Auth::user();

            return $payload ? (int) $payload->id : null;
        } catch (Throwable) {
            return null;
        }
    }
}
