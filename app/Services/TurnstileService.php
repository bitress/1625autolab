<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TurnstileService
{
    /**
     * Validate a Cloudflare Turnstile token via the Siteverify API.
     *
     * Preserves original behavior:
     *  - Bypass when TURNSTILE_BYPASS env is truthy.
     *  - Skip silently when no secret is configured (dev / test environments).
     *  - Fail-open on network error to avoid blocking real users.
     *  - Throw a 422 when token is missing or invalid.
     *
     * @param  array<string, mixed>  $data  Decoded JSON body; must contain 'cf-turnstile-response'.
     *
     * @throws HttpException 422 on CAPTCHA failure.
     */
    public function validate(array $data): void
    {
        if (filter_var(config('services.turnstile.bypass', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $secret = (string) config('services.turnstile.secret_key', '');

        // Skip validation when no secret is configured (dev / test environments).
        if ($secret === '') {
            return;
        }

        $token = trim((string) ($data['cf-turnstile-response'] ?? ''));

        if ($token === '') {
            abort(422, 'CAPTCHA token is required. Please complete the challenge.');
        }

        $ip = $this->resolveClientIp();

        $postData = [
            'secret' => $secret,
            'response' => $token,
        ];

        if ($ip !== '') {
            $postData['remoteip'] = $ip;
        }

        try {
            $response = Http::timeout(5)->asForm()->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                $postData
            );

            $result = $response->json();
        } catch (\Throwable $e) {
            // Network failure – fail open to avoid blocking real users.
            Log::warning('Turnstile: siteverify request failed (network).', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (! is_array($result) || empty($result['success'])) {
            $codes = implode(', ', (array) ($result['error-codes'] ?? ['unknown']));
            Log::warning('Turnstile validation failed.', ['codes' => $codes]);
            abort(422, 'CAPTCHA verification failed. Please try again.');
        }
    }

    /**
     * Resolve the real client IP, preferring Cloudflare's header.
     * Mirrors the original getClientIp() helper exactly.
     */
    private function resolveClientIp(): string
    {
        $candidates = [
            request()->header('CF-Connecting-IP'),
            request()->header('X-Forwarded-For'),
            request()->ip(),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $first = trim(explode(',', $candidate)[0]);
            if ($first !== '') {
                return substr($first, 0, 64);
            }
        }

        return '';
    }
}
