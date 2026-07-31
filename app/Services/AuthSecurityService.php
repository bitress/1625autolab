<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AuthSecurityService
{
    private const WINDOW_SECONDS = 900; // 15 minutes

    private const ACCOUNT_BLOCK_THRESHOLD = 8;

    private const IP_BLOCK_THRESHOLD = 20;

    private const SUSPICIOUS_ACCOUNT_THRESHOLD = 5;

    private const SUSPICIOUS_IP_THRESHOLD = 10;

    private ?bool $auditTableExistsCache = null;

    private ?bool $sessionsTableExistsCache = null;

    public function recordLoginAttempt(
        string $email,
        bool $success,
        ?int $userId,
        string $ipAddress,
        string $userAgent,
        ?string $detail = null
    ): void {
        if (! $this->auditTableExists()) {
            return;
        }

        $eventType = $success ? 'login_success' : 'login_failed';
        $outcome = $success ? 'success' : 'failure';

        try {
            DB::table('auth_audit_logs')->insert([
                'user_id' => $userId,
                'email' => strtolower(trim($email)),
                'ip_address' => $this->normalizeIp($ipAddress),
                'user_agent' => $this->normalizeUserAgent($userAgent),
                'event_type' => $eventType,
                'outcome' => $outcome,
                'detail' => $detail !== null ? trim($detail) : null,
                'created_at' => Carbon::now(),
            ]);
        } catch (\Throwable) {
            $this->auditTableExistsCache = false;
        }
    }

    public function isSuspiciousLogin(string $email, string $ipAddress): bool
    {
        $email = strtolower(trim($email));
        $ip = $this->normalizeIp($ipAddress);

        $emailFails = $email !== ''
            ? $this->countRecentFailedAttemptsBy('email', $email, self::WINDOW_SECONDS)
            : 0;
        $ipFails = $ip !== ''
            ? $this->countRecentFailedAttemptsBy('ip_address', $ip, self::WINDOW_SECONDS)
            : 0;

        return $emailFails >= self::SUSPICIOUS_ACCOUNT_THRESHOLD
            || $ipFails >= self::SUSPICIOUS_IP_THRESHOLD;
    }

    public function isTemporarilyBlocked(string $email, string $ipAddress): bool
    {
        return $this->getRetryAfterSeconds($email, $ipAddress) > 0;
    }

    public function getRetryAfterSeconds(string $email, string $ipAddress): int
    {
        if (! $this->auditTableExists()) {
            return 0;
        }

        $email = strtolower(trim($email));
        $ip = $this->normalizeIp($ipAddress);

        $accountRetry = $email !== ''
            ? $this->computeRetryAfterBy('email', $email, self::ACCOUNT_BLOCK_THRESHOLD, self::WINDOW_SECONDS)
            : 0;
        $ipRetry = $ip !== ''
            ? $this->computeRetryAfterBy('ip_address', $ip, self::IP_BLOCK_THRESHOLD, self::WINDOW_SECONDS)
            : 0;

        return max($accountRetry, $ipRetry);
    }

    public function recordBlockedLoginAttempt(
        string $email,
        string $ipAddress,
        string $userAgent,
        int $retryAfterSeconds,
        ?string $detail = null
    ): void {
        if (! $this->auditTableExists()) {
            return;
        }

        $suffix = $detail !== null && trim($detail) !== ''
            ? ' '.trim($detail)
            : '';

        try {
            DB::table('auth_audit_logs')->insert([
                'user_id' => null,
                'email' => strtolower(trim($email)),
                'ip_address' => $this->normalizeIp($ipAddress),
                'user_agent' => $this->normalizeUserAgent($userAgent),
                'event_type' => 'login_blocked',
                'outcome' => 'blocked',
                'detail' => 'Login temporarily blocked. Retry after '.max(1, $retryAfterSeconds).' second(s).'.$suffix,
                'created_at' => Carbon::now(),
            ]);
        } catch (\Throwable) {
            $this->auditTableExistsCache = false;
        }
    }

    public function shouldSendSuspiciousAlert(string $email, string $ipAddress): bool
    {
        if (! $this->auditTableExists()) {
            return false;
        }

        $email = strtolower(trim($email));
        $ip = $this->normalizeIp($ipAddress);
        $cutoff = Carbon::now()->subSeconds(self::WINDOW_SECONDS)->toDateTimeString();

        $count = DB::table('auth_audit_logs')
            ->where('event_type', 'suspicious_login_alert')
            ->where('created_at', '>=', $cutoff)
            ->where(function ($query) use ($email, $ip) {
                $query->where('email', $email)
                    ->orWhere('ip_address', $ip);
            })
            ->count();

        return $count === 0;
    }

    public function markSuspiciousAlertSent(string $email, string $ipAddress, string $userAgent, string $detail): void
    {
        if (! $this->auditTableExists()) {
            return;
        }

        try {
            DB::table('auth_audit_logs')->insert([
                'user_id' => null,
                'email' => strtolower(trim($email)),
                'ip_address' => $this->normalizeIp($ipAddress),
                'user_agent' => $this->normalizeUserAgent($userAgent),
                'event_type' => 'suspicious_login_alert',
                'outcome' => 'warning',
                'detail' => trim($detail),
                'created_at' => Carbon::now(),
            ]);
        } catch (\Throwable) {
            $this->auditTableExistsCache = false;
        }
    }

    public function createSession(
        int $userId,
        string $token,
        int $expUnix,
        string $ipAddress,
        string $userAgent
    ): void {
        if (! $this->sessionsTableExists()) {
            return;
        }

        $tokenHash = hash('sha256', $token);
        $now = Carbon::now();

        try {
            DB::table('auth_sessions')->insert([
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'ip_address' => $this->normalizeIp($ipAddress),
                'user_agent' => $this->normalizeUserAgent($userAgent),
                'issued_at' => $now,
                'expires_at' => Carbon::createFromTimestamp($expUnix),
                'last_seen_at' => $now,
            ]);
        } catch (\Throwable) {
            $this->sessionsTableExistsCache = false;
        }
    }

    public function listSessions(int $userId, ?string $currentTokenHash = null, int $limit = 20): array
    {
        if (! $this->sessionsTableExists()) {
            return [];
        }

        $limit = max(1, min(100, $limit));

        $rows = DB::table('auth_sessions')
            ->where('user_id', $userId)
            ->orderBy('issued_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return array_map(function ($row) use ($currentTokenHash): array {
            $tokenHash = (string) ($row->token_hash ?? '');

            return [
                'id' => (int) ($row->id ?? 0),
                'userId' => (int) ($row->user_id ?? 0),
                'ipAddress' => (string) ($row->ip_address ?? ''),
                'userAgent' => (string) ($row->user_agent ?? ''),
                'issuedAt' => (string) ($row->issued_at ?? ''),
                'expiresAt' => (string) ($row->expires_at ?? ''),
                'lastSeenAt' => (string) ($row->last_seen_at ?? ''),
                'revokedAt' => $row->revoked_at !== null ? (string) $row->revoked_at : null,
                'revokedReason' => $row->revoked_reason !== null ? (string) $row->revoked_reason : null,
                'isCurrent' => $currentTokenHash !== null && hash_equals($currentTokenHash, $tokenHash),
                'isActive' => $row->revoked_at === null,
            ];
        }, $rows->toArray());
    }

    public function revokeSessionById(int $userId, int $sessionId, string $reason = 'manual_revoke'): bool
    {
        if (! $this->sessionsTableExists()) {
            return false;
        }

        $row = DB::table('auth_sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->first();

        if (! $row) {
            return false;
        }

        DB::table('auth_sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => Carbon::now(),
                'revoked_reason' => $reason,
            ]);

        $this->blocklistTokenHash((string) ($row->token_hash ?? ''), (string) ($row->expires_at ?? ''));

        return true;
    }

    public function revokeOtherSessions(int $userId, string $currentTokenHash): int
    {
        if (! $this->sessionsTableExists()) {
            return 0;
        }

        $rows = DB::table('auth_sessions')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where('token_hash', '<>', $currentTokenHash)
            ->get(['id', 'token_hash', 'expires_at']);

        if ($rows->isEmpty()) {
            return 0;
        }

        foreach ($rows as $row) {
            $this->blocklistTokenHash((string) ($row->token_hash ?? ''), (string) ($row->expires_at ?? ''));
        }

        $affected = DB::table('auth_sessions')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where('token_hash', '<>', $currentTokenHash)
            ->update([
                'revoked_at' => Carbon::now(),
                'revoked_reason' => 'revoke_others',
            ]);

        return $affected;
    }

    public function endSessionByToken(string $token, string $reason = 'logout'): void
    {
        if (! $this->sessionsTableExists()) {
            return;
        }

        $hash = hash('sha256', $token);

        DB::table('auth_sessions')
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => Carbon::now(),
                'revoked_reason' => $reason,
            ]);
    }

    public function listAuthAuditLogs(int $limit = 200): array
    {
        if (! $this->auditTableExists()) {
            return [];
        }

        $limit = max(1, min(1000, $limit));

        try {
            $rows = DB::table('auth_audit_logs as l')
                ->select([
                    'l.id', 'l.user_id', 'l.email', 'l.ip_address', 'l.user_agent',
                    'l.event_type', 'l.outcome', 'l.detail', 'l.created_at',
                    'u.name as user_name',
                ])
                ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
                ->orderBy('l.created_at', 'desc')
                ->orderBy('l.id', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Throwable) {
            $this->auditTableExistsCache = false;

            return [];
        }

        return array_map(static function ($row): array {
            return [
                'id' => (int) ($row->id ?? 0),
                'userId' => $row->user_id !== null ? (int) $row->user_id : null,
                'userName' => $row->user_name !== null ? (string) $row->user_name : null,
                'email' => (string) ($row->email ?? ''),
                'ipAddress' => (string) ($row->ip_address ?? ''),
                'userAgent' => (string) ($row->user_agent ?? ''),
                'eventType' => (string) ($row->event_type ?? ''),
                'outcome' => (string) ($row->outcome ?? ''),
                'detail' => $row->detail !== null ? (string) $row->detail : null,
                'createdAt' => (string) ($row->created_at ?? ''),
            ];
        }, $rows->toArray());
    }

    private function countRecentFailedAttemptsBy(string $field, string $value, int $windowSeconds): int
    {
        if (! $this->auditTableExists()) {
            return 0;
        }

        if (! in_array($field, ['email', 'ip_address'], true) || $value === '') {
            return 0;
        }

        $cutoff = Carbon::now()->subSeconds(max(1, $windowSeconds))->toDateTimeString();

        return DB::table('auth_audit_logs')
            ->where('event_type', 'login_failed')
            ->where('created_at', '>=', $cutoff)
            ->where($field, $value)
            ->count();
    }

    private function computeRetryAfterBy(string $field, string $value, int $threshold, int $windowSeconds): int
    {
        if (! $this->auditTableExists()) {
            return 0;
        }

        if (! in_array($field, ['email', 'ip_address'], true) || $value === '' || $threshold <= 0) {
            return 0;
        }

        $windowSeconds = max(1, $windowSeconds);
        $cutoff = Carbon::now()->subSeconds($windowSeconds)->toDateTimeString();

        $subQuery = DB::table('auth_audit_logs')
            ->select('created_at')
            ->where('event_type', 'login_failed')
            ->where('created_at', '>=', $cutoff)
            ->where($field, $value)
            ->orderBy('created_at', 'desc')
            ->limit(max(1, $threshold));

        $row = DB::query()
            ->fromSub($subQuery, 't')
            ->selectRaw('COUNT(*) AS fail_count')
            ->selectRaw('GREATEST(0, CAST((UNIX_TIMESTAMP(MIN(t.created_at)) + ?) - UNIX_TIMESTAMP(NOW()) AS SIGNED)) AS retry_after', [$windowSeconds])
            ->first();

        if (! $row || ((int) ($row->fail_count ?? 0) < $threshold)) {
            return 0;
        }

        $retry = (int) ($row->retry_after ?? 0);

        return max(0, min($windowSeconds, $retry));
    }

    private function blocklistTokenHash(string $tokenHash, string $expiresAt): void
    {
        if ($tokenHash === '') {
            return;
        }

        $expiration = $expiresAt !== '' ? $expiresAt : Carbon::now()->addSeconds(config('jwt.ttl', 3600))->toDateTimeString();

        DB::table('token_blocklist')->insertOrIgnore([
            'token_hash' => $tokenHash,
            'expires_at' => $expiration,
        ]);
    }

    private function normalizeIp(string $ipAddress): string
    {
        $ip = trim($ipAddress);
        if ($ip === '') {
            return '';
        }

        return substr($ip, 0, 64);
    }

    private function normalizeUserAgent(string $userAgent): string
    {
        $ua = trim($userAgent);
        if ($ua === '') {
            return '';
        }

        return mb_substr($ua, 0, 500);
    }

    private function auditTableExists(): bool
    {
        if ($this->auditTableExistsCache !== null) {
            return $this->auditTableExistsCache;
        }

        try {
            DB::table('auth_audit_logs')->selectRaw('1')->limit(1)->first();
            $this->auditTableExistsCache = true;
        } catch (\Throwable) {
            $this->auditTableExistsCache = false;
        }

        return $this->auditTableExistsCache;
    }

    private function sessionsTableExists(): bool
    {
        if ($this->sessionsTableExistsCache !== null) {
            return $this->sessionsTableExistsCache;
        }

        try {
            DB::table('auth_sessions')->selectRaw('1')->limit(1)->first();
            $this->sessionsTableExistsCache = true;
        } catch (\Throwable) {
            $this->sessionsTableExistsCache = false;
        }

        return $this->sessionsTableExistsCache;
    }
}
