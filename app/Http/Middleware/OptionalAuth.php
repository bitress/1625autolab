<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OptionalAuth middleware.
 *
 * Resolves the authenticated user from the Bearer token when present,
 * but does NOT abort the request if no token is provided.
 *
 * Used for routes that have different behavior for authenticated vs.
 * anonymous users (e.g. service list shows inactive entries to admins).
 */
class OptionalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Attempt to authenticate; swallow the exception if no token is present.
        try {
            auth('sanctum')->authenticate();
        } catch (AuthenticationException) {
            // Anonymous request — carry on.
        }

        return $next($request);
    }
}
