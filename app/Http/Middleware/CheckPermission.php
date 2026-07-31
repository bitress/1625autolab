<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckPermission middleware.
 *
 * Usage in routes:
 *   ->middleware('permission:bookings:manage')
 *
 * Mirrors the original requirePermission() helper from Router.php.
 * admin and owner roles always pass (see PermissionService::hasPermissionByRole).
 */
class CheckPermission
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $role = (string) ($user->role ?? '');

        if (! $this->permissions->hasPermissionByRole($role, $permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        return $next($request);
    }
}
