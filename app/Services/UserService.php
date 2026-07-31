<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Carbon\Carbon;

class UserService
{
    /**
     * Look up a user by email, verify the password, rehash on-the-fly if
     * needed, and return a signed Sanctum token on success.
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
            'role'  => $user->role,
        ]);

        // Issue Sanctum token instead of JWT
        $token = $user->createToken('access')->plainTextToken;

        return [
            'token' => $token,
            'user'  => $user->toArray(),
        ];
    }

    /**
     * Validate registration fields, hash the password, and insert the new user record.
     *
     * @throws RuntimeException
     */
    public function register(array $data): array
    {
        $email = strtolower(trim((string) $data['email']));
        $phone = self::normalizePhoneForStorage((string) ($data['phone'] ?? ''));

        // Note: Validation is now handled by RegisterRequest, 
        // including uniqueness, length, and format checks.

        /** @var User $user */
        $user = User::create([
            'name'     => trim((string) $data['name']),
            'email'    => $email,
            'phone'    => $phone,
            'password' => Hash::make((string) $data['password']),
            'role'     => 'client',
        ]);

        $this->logActivity('User registered', $user, [
            'email' => $user->email,
        ]);

        return $user->toArray();
    }

    /**
     * Normalize PH mobile formats to local 11-digit form (09XXXXXXXXX).
     */
    private static function normalizePhoneForStorage(string $phone): string
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
            return '0' . substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0' . $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11 && ($digits[1] ?? '') === '9') {
            return $digits;
        }

        return $trimmed;
    }

    /**
     * Generate and store a password-reset token for the given email.
     * Returns the token or null if email not found.
     */
    public function generatePasswordResetToken(string $email): ?string
    {
        $email = strtolower(trim($email));
        
        $user = User::where('email', $email)->first();
        if (! $user) {
            return null; // Email not found – suppress the fact for security
        }

        DB::table('password_resets')->where('email', $email)->delete();

        $token = bin2hex(random_bytes(32)); // 64-char hex string
        $expiresAt = Carbon::now()->addHour();

        DB::table('password_resets')->insert([
            'email'      => $email,
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Verify a password-reset token and update the user's password.
     *
     * @throws RuntimeException
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
            // User was deleted but token remained
            throw new RuntimeException('User not found.', 422);
        }

        DB::table('password_resets')->where('token', $token)->delete();
    }

    /**
     * Generate password reset token and dispatch email.
     */
    public function sendPasswordResetLink(string $email): void
    {
        $token = $this->generatePasswordResetToken($email);
        
        if ($token) {
            $resetLink = rtrim(config('app.url'), '/') . '/reset-password?token=' . $token;
            
            // Dispatch notification job
            $jobQueue = app(\App\Services\NotificationJobQueueService::class);
            $jobQueue->dispatch('password_reset_requested', [
                'email'     => $email,
                'resetLink' => $resetLink,
            ]);
        }
    }
    
    /**
     * Verify email via token.
     */
    public function verifyEmail(string $token): array
    {
        // TODO: Implement email verification token logic if necessary
        // For now, throw exception as stub
        throw new RuntimeException('Email verification not fully implemented.');
    }

    /**
     * Resend email verification.
     */
    public function resendEmailVerification(int $userId): void
    {
        // TODO: Implement logic
    }
    
    /**
     * Update user profile.
     */
    public function updateProfile(int $userId, array $data): array
    {
        $user = User::findOrFail($userId);
        
        // Update allowable fields
        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        
        if (isset($data['phone'])) {
            $user->phone = self::normalizePhoneForStorage((string)$data['phone']);
        }
        
        $user->save();
        
        return $user->toArray();
    }
    
    /**
     * Centralized activity logger wrapper that replicates Spatie Activitylog usage 
     * from the legacy codebase (`activity()`).
     */
    private function logActivity(string $description, User $user, array $properties = []): void
    {
        try {
            if (function_exists('activity')) {
                activity('auth')
                    ->performedOn($user)
                    ->by($user)
                    ->withProperties($properties)
                    ->log($description);
            }
        } catch (\Throwable $e) {
            error_log('[Auth] Activity logging failed: ' . $e->getMessage());
        }
    }
    
    // --- Admin specific methods below ---
    
    public function listUsers(array $filters): array
    {
        // Implementation stub
        return [];
    }
    
    public function listClients(array $filters): array
    {
        return [];
    }
    
    public function listRoles(): array
    {
        return [];
    }
    
    public function listRoleAuditLogs(int $limit): array
    {
        return [];
    }
    
    public function createRole(array $data, int $adminId, string $adminName): array
    {
        return [];
    }
    
    public function updateRoleDefinition(int $roleId, array $data, int $adminId, string $adminName): array
    {
        return [];
    }
    
    public function deleteRole(int $roleId, int $adminId, string $adminName): void
    {
    }
    
    public function createByAdmin(array $data): array
    {
        return [];
    }
    
    public function updateRole(int $userId, string $role, int $adminId, string $adminName): array
    {
        return [];
    }
    
    public function updateUserStatus(int $userId, bool $isActive): array
    {
        return [];
    }
    
    public function updateUserInfo(int $userId, array $data): array
    {
        return [];
    }
    
    public function deleteByAdmin(int $userId, int $adminId, string $adminName): void
    {
    }
}
