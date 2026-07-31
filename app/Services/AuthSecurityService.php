<?php

declare(strict_types=1);

namespace App\Services;

class AuthSecurityService
{
    /**
     * Handle post-login security: check suspicious activity, rate limits, session logging.
     *
     * @param  array<string, mixed>  $user
     */
    public function onLoginSuccess(array $user, string $ip, string $userAgent): void
    {
        // TODO: mirror the original onLoginSuccess() logic.
        throw new \RuntimeException('AuthSecurityService::onLoginSuccess() not implemented.', 501);
    }

    /**
     * Handle failed login attempt for rate limiting / lockout.
     */
    public function onLoginFailure(string $email, string $ip): void
    {
        // TODO: mirror onLoginFailure() logic from the original Router.
        throw new \RuntimeException('AuthSecurityService::onLoginFailure() not implemented.', 501);
    }

    /**
     * Return recent auth audit log entries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAuthAuditLogs(int $limit): array
    {
        throw new \RuntimeException('AuthSecurityService::listAuthAuditLogs() not implemented.', 501);
    }
}
