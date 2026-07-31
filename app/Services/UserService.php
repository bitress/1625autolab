<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserService
{
    private const EMAIL_VERIFICATION_TOKEN_TTL = 86400;

    /**
     * Authenticate with email + password and return a signed Sanctum token + user data.
     *
     * @throws RuntimeException
     */
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new RuntimeException('Invalid email or password.', 401);
        }

        if (isset($user->is_active) && ! (bool) $user->is_active) {
            throw new RuntimeException('This account has been deactivated. Please contact support.', 403);
        }

        if (array_key_exists('email_verified_at', $user->getAttributes()) && $user->email_verified_at === null) {
            throw new RuntimeException('Please verify your email address before signing in.', 403);
        }

        if (Hash::needsRehash($user->password)) {
            $user->password = Hash::make($password);
            $user->save();
        }

        $this->logActivity('User logged in', $user, [
            'email' => $user->email,
            'role' => $user->role,
        ]);

        $token = $user->createToken('access')->plainTextToken;

        return [
            'token' => $token,
            'user' => $this->findById($user->id),
        ];
    }

    /**
     * Register a new client account and trigger email verification.
     *
     * @throws RuntimeException
     */
    public function register(array $data): array
    {
        $email = strtolower(trim((string) $data['email']));
        $phone = self::normalizePhoneForStorage((string) ($data['phone'] ?? ''));

        // Validation handled by Form Request (RegisterRequest) prior to this call

        /** @var User $user */
        $user = User::create([
            'name' => trim((string) $data['name']),
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make((string) $data['password']),
            'role' => 'client',
        ]);

        $this->logActivity('User registered', $user, [
            'email' => $user->email,
        ]);

        $userData = $this->findById($user->id);
        $this->issueEmailVerificationForUser($userData);
        $this->claimAnonymousBookings($user->id, $email);

        return [
            'message' => 'Registration successful. Please verify your email before signing in.',
            'verification_required' => true,
            'user' => $userData,
        ];
    }

    /**
     * Generate password reset token and dispatch email.
     */
    public function sendPasswordResetLink(string $email): void
    {
        $token = $this->generatePasswordResetToken($email);

        if ($token) {
            $resetLink = rtrim(config('app.url'), '/').'/reset-password?token='.$token;

            $jobQueue = app(NotificationJobQueueService::class);
            // Assuming the NotificationJobQueueService implements dispatch method
            if (method_exists($jobQueue, 'dispatch')) {
                $jobQueue->dispatch('password_reset_requested', [
                    'email' => $email,
                    'resetLink' => $resetLink,
                ]);
            }
        }
    }

    /**
     * Generate and store a password-reset token for the given email.
     */
    public function generatePasswordResetToken(string $email): ?string
    {
        $email = strtolower(trim($email));

        $user = User::where('email', $email)->first();
        if (! $user) {
            return null;
        }

        DB::table('password_resets')->where('email', $email)->delete();

        $token = bin2hex(random_bytes(32));
        $expiresAt = Carbon::now()->addHour();

        DB::table('password_resets')->insert([
            'email' => $email,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Verify a password-reset token and update the user's password.
     */
    public function resetPassword(array $data): void
    {
        $token = trim($data['token'] ?? '');
        $newPassword = $data['password'] ?? '';

        $resetRecord = DB::table('password_resets')
            ->where('token', $token)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (! $resetRecord) {
            throw new RuntimeException('This reset link is invalid or has expired.', 422);
        }

        $email = $resetRecord->email;

        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->password = Hash::make($newPassword);
            $user->save();

            $this->logActivity('User password reset', $user, [
                'email' => $user->email,
            ]);
        } else {
            throw new RuntimeException('User not found.', 422);
        }

        DB::table('password_resets')->where('token', $token)->delete();
    }

    public function findById(int $id): array
    {
        $user = User::find($id);

        if (! $user) {
            throw new RuntimeException('User not found.', 404);
        }

        return $this->sanitize($user->toArray());
    }

    public function updateProfile(int $id, array $data): array
    {
        $fields = [];
        $emailChanged = false;
        $nextEmail = '';

        /** @var User $user */
        $user = User::findOrFail($id);
        $oldAvatar = $user->avatar_url ?? '';

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $user->name = trim((string) $data['name']);
            $fields[] = 'name';
        }

        if (array_key_exists('phone', $data) && $data['phone'] !== null) {
            $user->phone = $this->normalizePhoneForStorage((string) $data['phone']);
            $fields[] = 'phone';
        }

        if (array_key_exists('avatar_url', $data)) {
            $user->avatar_url = $data['avatar_url'] !== null ? trim((string) $data['avatar_url']) : null;
            $fields[] = 'avatar_url';
        }

        if (array_key_exists('email', $data)) {
            $email = strtolower(trim((string) $data['email']));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('A valid email address is required.', 422);
            }

            if ($email !== strtolower(trim($user->email))) {
                if (User::where('email', $email)->where('id', '<>', $id)->exists()) {
                    throw new RuntimeException('That email address is already registered.', 409);
                }

                $user->email = $email;
                $user->email_verified_at = null;
                $emailChanged = true;
                $nextEmail = $email;
                $fields[] = 'email';
            }
        }

        $newPw = $data['password'] ?? '';
        if ($newPw !== '') {
            if (strlen($newPw) < 8) {
                throw new RuntimeException('Password must be at least 8 characters.', 422);
            }
            if (($data['password_confirmation'] ?? '') !== $newPw) {
                throw new RuntimeException('Password confirmation does not match.', 422);
            }
            $user->password = Hash::make($newPw);
            $fields[] = 'password';
        }

        if (! empty($fields)) {
            $user->save();
        }

        $updated = $this->findById($id);
        $newAvatar = $updated['avatar_url'] ?? '';

        if ($oldAvatar !== '' && $oldAvatar !== $newAvatar) {
            $this->deleteManagedImageUrl($oldAvatar);
        }

        if ($emailChanged && $nextEmail !== '') {
            $this->issueEmailVerificationForUser($updated);
        }

        return $updated;
    }

    public function resendEmailVerification(int $userId): void
    {
        $user = $this->findById($userId);
        if ($user['email_verified'] ?? false) {
            return;
        }

        $this->issueEmailVerificationForUser($user);
    }

    public function verifyEmail(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new RuntimeException('Verification token is required.', 422);
        }

        $row = DB::table('email_verifications')
            ->where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (! $row) {
            throw new RuntimeException('This verification link is invalid or has expired.', 422);
        }

        $verificationId = (int) $row->id;
        $userId = (int) $row->user_id;
        $email = strtolower(trim((string) $row->email));

        DB::beginTransaction();
        try {
            DB::table('users')
                ->where('id', $userId)
                ->where('email', $email)
                ->update(['email_verified_at' => Carbon::now()]);

            DB::table('email_verifications')
                ->where('id', $verificationId)
                ->update(['used_at' => Carbon::now()]);

            DB::table('email_verifications')
                ->where('user_id', $userId)
                ->whereNull('used_at')
                ->where('id', '<>', $verificationId)
                ->update(['used_at' => Carbon::now()]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        try {
            if (function_exists('activity')) {
                activity('auth')
                    ->performedOn(new User(['id' => $userId]))
                    ->causedBy(new User(['id' => $userId]))
                    ->withProperties(['email' => $email])
                    ->log('USER_UPDATED_PROFILE');
            }
        } catch (\Throwable $e) {
            error_log('[UserService] Email verify activity logging failed: '.$e->getMessage());
        }

        return $this->findById($userId);
    }

    public function listUsers(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $role = trim((string) ($filters['role'] ?? ''));

        $query = User::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role !== '') {
            if (! $this->roleExists($role)) {
                throw new RuntimeException('Invalid role filter.', 422);
            }
            $query->where('role', $role);
        }

        $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        $rows = $query->get()->toArray();

        return array_map([$this, 'sanitize'], $rows);
    }

    public function createByAdmin(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = $this->normalizePhoneForStorage((string) ($data['phone'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = strtolower(trim((string) ($data['role'] ?? 'client')));

        if ($name === '') {
            throw new RuntimeException('Name is required.', 422);
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email address is required.', 422);
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters.', 422);
        }
        if (! $this->roleExists($role)) {
            throw new RuntimeException('Invalid role.', 422);
        }

        if (User::where('email', $email)->exists()) {
            throw new RuntimeException('That email address is already registered.', 409);
        }

        /** @var User $user */
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($password),
            'role' => $role,
            'email_verified_at' => Carbon::now(),
        ]);

        return $this->findById($user->id);
    }

    public function updateRole(int $id, string $role, ?int $actorUserId = null, ?string $actorName = null): array
    {
        $role = strtolower(trim($role));
        if (! $this->roleExists($role)) {
            throw new RuntimeException('Invalid role.', 422);
        }

        $current = $this->findById($id);
        $previousRole = strtolower(trim((string) ($current['role'] ?? '')));
        if ($previousRole === $role) {
            return $current;
        }

        $user = User::findOrFail($id);
        $user->role = $role;
        $user->save();

        $updated = $this->findById($id);

        $this->logRoleAudit(
            'user_role_updated',
            null,
            $role,
            $id,
            $actorUserId,
            $actorName,
            [
                'from' => $previousRole,
                'to' => $role,
                'targetEmail' => (string) ($updated['email'] ?? ''),
                'targetName' => (string) ($updated['name'] ?? ''),
            ]
        );

        return $updated;
    }

    public function listRoles(): array
    {
        $rows = DB::table('roles')
            ->orderBy('is_system', 'desc')
            ->orderBy('name', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return array_map(function ($row) {
            $decoded = json_decode((string) ($row->permissions_json ?? '[]'), true);
            $permissions = is_array($decoded)
                ? array_values(array_filter(array_map('strval', $decoded), static fn (string $v): bool => $v !== ''))
                : [];

            return [
                'id' => (int) ($row->id ?? 0),
                'key' => (string) ($row->role_key ?? ''),
                'name' => (string) ($row->name ?? ''),
                'description' => (string) ($row->description ?? ''),
                'permissions' => $permissions,
                'isSystem' => ((int) ($row->is_system ?? 0)) === 1,
                'created_at' => $row->created_at ?? null,
                'updated_at' => $row->updated_at ?? null,
            ];
        }, $rows->all());
    }

    public function createRole(array $data, ?int $actorUserId = null, ?string $actorName = null): array
    {
        $key = $this->normalizeRoleKey((string) ($data['key'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $permissions = $this->normalizePermissions($data['permissions'] ?? []);

        if ($key === '') {
            throw new RuntimeException('Role key is required.', 422);
        }
        if (! preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $key)) {
            throw new RuntimeException('Role key must be 2-32 characters: lowercase letters, numbers, underscore, dash.', 422);
        }
        if ($name === '') {
            throw new RuntimeException('Role name is required.', 422);
        }

        if (DB::table('roles')->where('role_key', $key)->exists()) {
            throw new RuntimeException('Role key already exists.', 409);
        }

        $id = DB::table('roles')->insertGetId([
            'role_key' => $key,
            'name' => $name,
            'description' => $description,
            'permissions_json' => json_encode($permissions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_system' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $created = $this->getRoleById((int) $id);

        $this->logRoleAudit(
            'role_created',
            (int) ($created['id'] ?? 0),
            (string) ($created['key'] ?? ''),
            null,
            $actorUserId,
            $actorName,
            [
                'name' => (string) ($created['name'] ?? ''),
                'permissions' => (array) ($created['permissions'] ?? []),
                'isSystem' => (bool) ($created['isSystem'] ?? false),
            ]
        );

        return $created;
    }

    public function updateRoleDefinition(int $id, array $data, ?int $actorUserId = null, ?string $actorName = null): array
    {
        $current = $this->getRoleById($id);
        $nextKey = $this->normalizeRoleKey((string) ($data['key'] ?? $current['key']));
        $nextName = trim((string) ($data['name'] ?? $current['name']));
        $nextDescription = trim((string) ($data['description'] ?? $current['description']));
        $nextPermissions = array_key_exists('permissions', $data)
            ? $this->normalizePermissions($data['permissions'])
            : (array) ($current['permissions'] ?? []);

        if ($nextKey === '' || ! preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $nextKey)) {
            throw new RuntimeException('Role key must be 2-32 characters: lowercase letters, numbers, underscore, dash.', 422);
        }
        if ($nextName === '') {
            throw new RuntimeException('Role name is required.', 422);
        }

        if ((bool) ($current['isSystem'] ?? false) && $nextKey !== (string) $current['key']) {
            throw new RuntimeException('System role keys cannot be changed.', 422);
        }

        if ($nextKey !== (string) $current['key']) {
            if (DB::table('roles')->where('role_key', $nextKey)->where('id', '<>', $id)->exists()) {
                throw new RuntimeException('Role key already exists.', 409);
            }
        }

        DB::beginTransaction();
        try {
            DB::table('roles')
                ->where('id', $id)
                ->update([
                    'role_key' => $nextKey,
                    'name' => $nextName,
                    'description' => $nextDescription,
                    'permissions_json' => json_encode($nextPermissions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => Carbon::now(),
                ]);

            if ($nextKey !== (string) $current['key']) {
                User::where('role', (string) $current['key'])->update(['role' => $nextKey]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $updated = $this->getRoleById($id);

        $this->logRoleAudit(
            'role_updated',
            (int) ($updated['id'] ?? 0),
            (string) ($updated['key'] ?? ''),
            null,
            $actorUserId,
            $actorName,
            [
                'before' => [
                    'key' => (string) ($current['key'] ?? ''),
                    'name' => (string) ($current['name'] ?? ''),
                    'description' => (string) ($current['description'] ?? ''),
                    'permissions' => (array) ($current['permissions'] ?? []),
                ],
                'after' => [
                    'key' => (string) ($updated['key'] ?? ''),
                    'name' => (string) ($updated['name'] ?? ''),
                    'description' => (string) ($updated['description'] ?? ''),
                    'permissions' => (array) ($updated['permissions'] ?? []),
                ],
            ]
        );

        return $updated;
    }

    public function deleteRole(int $id, ?int $actorUserId = null, ?string $actorName = null): void
    {
        $role = $this->getRoleById($id);

        if ((bool) ($role['isSystem'] ?? false)) {
            throw new RuntimeException('System roles cannot be deleted.', 422);
        }

        $inUseCount = User::where('role', (string) $role['key'])->count();
        if ($inUseCount > 0) {
            throw new RuntimeException('Cannot delete a role that is assigned to users.', 409);
        }

        DB::table('roles')->where('id', $id)->delete();

        $this->logRoleAudit(
            'role_deleted',
            $id,
            (string) ($role['key'] ?? ''),
            null,
            $actorUserId,
            $actorName,
            [
                'name' => (string) ($role['name'] ?? ''),
                'description' => (string) ($role['description'] ?? ''),
                'permissions' => (array) ($role['permissions'] ?? []),
            ]
        );
    }

    public function listRoleAuditLogs(int $limit = 100): array
    {
        if (! $this->roleAuditTableExists()) {
            return [];
        }

        $limit = max(1, min(300, $limit));

        $rows = DB::table('role_audit_logs as ral')
            ->leftJoin('users as au', 'au.id', '=', 'ral.actor_user_id')
            ->leftJoin('users as tu', 'tu.id', '=', 'ral.target_user_id')
            ->select([
                'ral.id', 'ral.action', 'ral.role_id', 'ral.role_key', 'ral.target_user_id',
                'ral.actor_user_id', 'ral.actor_name', 'ral.details_json', 'ral.created_at',
                'au.email as actor_email', 'tu.email as target_email',
            ])
            ->orderBy('ral.created_at', 'desc')
            ->orderBy('ral.id', 'desc')
            ->limit($limit)
            ->get();

        return array_map(function ($row) {
            $details = json_decode((string) ($row->details_json ?? 'null'), true);

            return [
                'id' => (int) ($row->id ?? 0),
                'action' => (string) ($row->action ?? ''),
                'roleId' => isset($row->role_id) ? (int) $row->role_id : null,
                'roleKey' => isset($row->role_key) ? (string) $row->role_key : null,
                'targetUserId' => isset($row->target_user_id) ? (int) $row->target_user_id : null,
                'targetUserEmail' => isset($row->target_email) ? (string) $row->target_email : null,
                'actorUserId' => isset($row->actor_user_id) ? (int) $row->actor_user_id : null,
                'actorName' => (string) ($row->actor_name ?? ''),
                'actorEmail' => isset($row->actor_email) ? (string) $row->actor_email : null,
                'details' => is_array($details) ? $details : null,
                'created_at' => (string) ($row->created_at ?? ''),
            ];
        }, $rows->all());
    }

    public function listClients(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $query = DB::table('users as u')
            ->leftJoin('bookings as b', 'b.user_id', '=', 'u.id')
            ->select([
                'u.id', 'u.name', 'u.email', 'u.phone', 'u.role', 'u.is_active', 'u.created_at',
                DB::raw('COUNT(b.id) AS booking_count'),
                DB::raw('MAX(b.created_at) AS last_booking_at'),
            ])
            ->where('u.role', 'client')
            ->groupBy('u.id', 'u.name', 'u.email', 'u.phone', 'u.role', 'u.is_active', 'u.created_at')
            ->orderBy('u.created_at', 'desc')
            ->orderBy('u.id', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                    ->orWhere('u.email', 'like', "%{$search}%")
                    ->orWhere('u.phone', 'like', "%{$search}%");
            });
        }

        $rows = $query->get();

        return array_map(function ($row) {
            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'email' => (string) $row->email,
                'phone' => (string) ($row->phone ?? ''),
                'role' => (string) $row->role,
                'is_active' => isset($row->is_active) ? (bool) $row->is_active : true,
                'created_at' => (string) $row->created_at,
                'bookingCount' => (int) ($row->booking_count ?? 0),
                'lastBookingAt' => $row->last_booking_at !== null ? (string) $row->last_booking_at : null,
            ];
        }, $rows->all());
    }

    public function updateUserStatus(int $id, bool $isActive): array
    {
        $user = User::findOrFail($id);
        $user->is_active = $isActive;
        $user->save();

        return $this->findById($id);
    }

    public function updateUserInfo(int $id, array $data): array
    {
        $user = User::findOrFail($id);
        $changed = false;

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new RuntimeException('Name is required.', 422);
            }
            $user->name = $name;
            $changed = true;
        }

        if (array_key_exists('email', $data)) {
            $email = strtolower(trim((string) $data['email']));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('A valid email address is required.', 422);
            }
            if ($email !== $user->email && User::where('email', $email)->where('id', '<>', $id)->exists()) {
                throw new RuntimeException('That email address is already registered.', 409);
            }
            if ($email !== $user->email) {
                $user->email = $email;
                $user->email_verified_at = Carbon::now();
                $changed = true;
            }
        }

        if (array_key_exists('phone', $data)) {
            $user->phone = $this->normalizePhoneForStorage((string) $data['phone']);
            $changed = true;
        }

        if ($changed) {
            $user->save();
        }

        return $this->findById($id);
    }

    public function deleteByAdmin(int $id, ?int $actorUserId = null, ?string $actorName = null): void
    {
        $target = $this->findById($id);
        $targetRole = strtolower(trim((string) ($target['role'] ?? '')));

        if ($targetRole === 'owner' && $this->countUsersByRole('owner') <= 1) {
            throw new RuntimeException('You cannot delete the last owner account.', 422);
        }

        DB::beginTransaction();
        try {
            // Wait, we need PrivacyService::deleteAccount logic if it exists.
            if (class_exists(PrivacyService::class)) {
                (new PrivacyService)->deleteAccount($id, 'admin_delete');
            } else {
                User::where('id', $id)->delete();
            }

            $this->logRoleAudit(
                'user_deleted',
                null,
                $targetRole !== '' ? $targetRole : null,
                $id,
                $actorUserId,
                $actorName,
                [
                    'targetEmail' => (string) ($target['email'] ?? ''),
                    'targetName' => (string) ($target['name'] ?? ''),
                    'targetRole' => $targetRole,
                ]
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function sanitize(array $row): array
    {
        unset($row['password']);
        $row['avatar_url'] = $row['avatar_url'] ?? null;
        $row['is_active'] = isset($row['is_active']) ? (bool) $row['is_active'] : true;
        $row['email_verified'] = isset($row['email_verified_at']) && $row['email_verified_at'] !== null;

        return $row;
    }

    private function issueEmailVerificationForUser(array $user): void
    {
        $userId = (int) ($user['id'] ?? 0);
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $name = (string) ($user['name'] ?? '');

        if ($userId <= 0 || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            DB::table('email_verifications')
                ->where('user_id', $userId)
                ->whereNull('used_at')
                ->delete();

            $token = bin2hex(random_bytes(32));
            $expiresAt = Carbon::now()->addSeconds(self::EMAIL_VERIFICATION_TOKEN_TTL);

            DB::table('email_verifications')->insert([
                'user_id' => $userId,
                'email' => $email,
                'token' => $token,
                'expires_at' => $expiresAt,
            ]);

            $verifyUrl = rtrim(config('app.url'), '/').'/login?verifyToken='.urlencode($token);

            // Dispatch to NotificationJobQueueService if it exists
            $jobQueue = app(NotificationJobQueueService::class);
            if (method_exists($jobQueue, 'dispatch')) {
                $jobQueue->dispatch('email_verification', [
                    'email' => $email,
                    'name' => $name,
                    'verifyUrl' => $verifyUrl,
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[UserService] Email verification dispatch failed: '.$e->getMessage());
        }
    }

    private function deleteManagedImageUrl(string $url): void
    {
        try {
            if (class_exists(UploadStorageService::class)) {
                app(UploadStorageService::class)->deleteByUrl($url);
            }
        } catch (\Throwable) {
            // Keep profile updates successful even if storage cleanup fails.
        }
    }

    private function roleExists(string $role): bool
    {
        $role = $this->normalizeRoleKey($role);
        if ($role === '') {
            return false;
        }

        return DB::table('roles')->where('role_key', $role)->exists();
    }

    private function countUsersByRole(string $role): int
    {
        return User::where('role', strtolower(trim($role)))->count();
    }

    private function normalizePhoneForStorage(string $phone): string
    {
        $trimmed = trim($phone);
        if ($trimmed === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if ($digits === null || $digits === '') {
            return $trimmed;
        }

        if (str_starts_with($digits, '63') && strlen($digits) === 12 && ($digits[2] ?? '') === '9') {
            return '0'.substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0'.$digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11 && ($digits[1] ?? '') === '9') {
            return $digits;
        }

        return $trimmed;
    }

    private function getRoleById(int $id): array
    {
        $row = DB::table('roles')->where('id', $id)->first();

        if (! $row) {
            throw new RuntimeException('Role not found.', 404);
        }

        $decoded = json_decode((string) ($row->permissions_json ?? '[]'), true);
        $permissions = is_array($decoded)
            ? array_values(array_filter(array_map('strval', $decoded), static fn (string $v): bool => $v !== ''))
            : [];

        return [
            'id' => (int) ($row->id ?? 0),
            'key' => (string) ($row->role_key ?? ''),
            'name' => (string) ($row->name ?? ''),
            'description' => (string) ($row->description ?? ''),
            'permissions' => $permissions,
            'isSystem' => ((int) ($row->is_system ?? 0)) === 1,
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    private function normalizeRoleKey(string $value): string
    {
        return strtolower(trim($value));
    }

    private function normalizePermissions($permissions): array
    {
        if (! is_array($permissions)) {
            return [];
        }

        $result = [];
        foreach ($permissions as $permission) {
            $value = strtolower(trim((string) $permission));
            if ($value === '') {
                continue;
            }
            if (! preg_match('/^[a-z0-9:_-]{2,64}$/', $value)) {
                throw new RuntimeException('Invalid permission key format.', 422);
            }
            if (! in_array($value, $result, true)) {
                $result[] = $value;
            }
        }

        return $result;
    }

    private function roleAuditTableExists(): bool
    {
        try {
            DB::table('role_audit_logs')->limit(1)->count();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function logRoleAudit(
        string $action,
        ?int $roleId,
        ?string $roleKey,
        ?int $targetUserId,
        ?int $actorUserId,
        ?string $actorName,
        ?array $details = null
    ): void {
        if (! $this->roleAuditTableExists()) {
            return;
        }

        $safeRoleKey = $roleKey !== null ? trim($roleKey) : null;
        $safeActorName = trim((string) ($actorName ?? ''));

        DB::table('role_audit_logs')->insert([
            'action' => $action,
            'role_id' => $roleId,
            'role_key' => $safeRoleKey,
            'target_user_id' => $targetUserId,
            'actor_user_id' => $actorUserId,
            'actor_name' => $safeActorName,
            'details_json' => $details !== null
                ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            'created_at' => Carbon::now(),
        ]);

        try {
            if (function_exists('activity')) {
                $logger = activity('roles')
                    ->performedOn(new User(['id' => $roleId ?? 0])); // fallback for subject

                if ($actorUserId !== null && $actorUserId > 0) {
                    $logger->causedBy(new User(['id' => $actorUserId]));
                }

                $logger->withProperties([
                    'roleKey' => $safeRoleKey,
                    'targetUserId' => $targetUserId,
                    'actorName' => $safeActorName,
                    'details' => $details,
                ])->log($action);
            }
        } catch (\Throwable $e) {
            error_log('[UserService] Activity logging failed: '.$e->getMessage());
        }
    }

    private function claimAnonymousBookings(int $userId, string $email): void
    {
        if ($email === '') {
            return;
        }
        DB::table('bookings')
            ->where('email', $email)
            ->whereNull('user_id')
            ->update(['user_id' => $userId]);
    }

    private function logActivity(string $description, User $user, array $properties = []): void
    {
        try {
            if (function_exists('activity')) {
                activity('auth')
                    ->performedOn($user)
                    ->causedBy($user)
                    ->withProperties($properties)
                    ->log($description);
            }
        } catch (\Throwable $e) {
            error_log('[UserService] Activity logging failed: '.$e->getMessage());
        }
    }
}
