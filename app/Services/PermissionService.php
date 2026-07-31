<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PermissionService
{
    /**
     * Static fallback permissions map.
     * Mirrors FALLBACK_ROLE_PERMISSIONS from Router.php exactly.
     *
     * @var array<string, string[]>
     */
    private const FALLBACK_ROLE_PERMISSIONS = [
        'owner' => [
            'analytics:view',
            'bookings:manage',
            'bookings:assign-tech',
            'bookings:notes',
            'chatbot:manage',
            'build-updates:manage',
            'clients:manage',
            'users:manage',
            'roles:view',
            'roles:manage',
            'security:audit:view',
            'reviews:manage',
            'services:manage',
            'products:manage',
            'content:manage',
            'settings:manage',
            'shop-hours:manage',
            'media:upload',
        ],
        'admin' => [
            'analytics:view',
            'bookings:manage',
            'bookings:assign-tech',
            'bookings:notes',
            'chatbot:manage',
            'build-updates:manage',
            'clients:manage',
            'users:manage',
            'roles:view',
            'roles:manage',
            'reviews:manage',
            'services:manage',
            'products:manage',
            'content:manage',
            'settings:manage',
            'shop-hours:manage',
            'media:upload',
        ],
        'manager' => [
            'analytics:view',
            'bookings:manage',
            'bookings:assign-tech',
            'bookings:notes',
            'build-updates:manage',
            'clients:manage',
            'security:audit:view',
            'roles:view',
            'reviews:manage',
            'services:manage',
            'products:manage',
            'media:upload',
        ],
        'staff' => [
            'bookings:manage',
            'build-updates:manage',
            'clients:manage',
            'roles:view',
            'media:upload',
        ],
        'client' => ['client:self'],
    ];

    /** Cache TTL for the permission map (5 minutes). */
    private const CACHE_TTL = 300;

    private const CACHE_KEY = 'apollo_permission_map';

    /**
     * Return the full role → permissions map.
     * Tries to load dynamic roles from the DB via UserService; falls back to the static map.
     *
     * @return array<string, string[]>
     */
    public function getPermissionMap(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $map = self::FALLBACK_ROLE_PERMISSIONS;

            try {
                /** @var UserService $userService */
                $userService = app(UserService::class);
                $roles = $userService->listRoles();

                if (! empty($roles)) {
                    $dynamic = [];
                    foreach ($roles as $role) {
                        $key = strtolower(trim((string) ($role['key'] ?? '')));
                        if ($key === '') {
                            continue;
                        }

                        $permissions = is_array($role['permissions'] ?? null)
                            ? array_values(array_filter(
                                array_map('strval', $role['permissions']),
                                static fn (string $v): bool => $v !== ''
                            ))
                            : [];

                        $dynamic[$key] = $permissions;
                    }

                    if (! empty($dynamic)) {
                        $map = $dynamic;
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('PermissionService: falling back to static map.', [
                    'error' => $e->getMessage(),
                ]);
            }

            return $map;
        });
    }

    /**
     * Check whether a role has a given permission.
     * Mirrors hasPermissionByRole() — admin and owner bypass all permission checks.
     */
    public function hasPermissionByRole(string $role, string $permission): bool
    {
        $role = strtolower(trim($role));
        if ($role === '') {
            return false;
        }

        if ($role === 'admin' || $role === 'owner') {
            return true;
        }

        $permissions = $this->getPermissionMap()[$role] ?? [];

        return in_array($permission, $permissions, true);
    }

    /**
     * Check whether a user payload has a given permission.
     *
     * @param  array<string, mixed>  $payload
     */
    public function hasPermission(array $payload, string $permission): bool
    {
        return $this->hasPermissionByRole((string) ($payload['role'] ?? ''), $permission);
    }

    /**
     * Check whether a user payload has at least one of the given permissions.
     *
     * @param  array<string, mixed>  $payload
     * @param  string[]  $permissions
     */
    public function hasAnyPermission(array $payload, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($payload, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the effective permission list for a role.
     * admin and owner always receive chatbot:manage in addition to their stored permissions.
     *
     * @return string[]
     */
    public function getRolePermissions(string $role): array
    {
        $role = strtolower(trim($role));
        if ($role === '') {
            return [];
        }

        $permissions = $this->getPermissionMap()[$role] ?? [];

        if ($role === 'admin' || $role === 'owner') {
            $permissions = array_values(array_unique(array_merge($permissions, ['chatbot:manage'])));
        }

        return array_values(array_unique(array_map('strval', $permissions)));
    }

    /**
     * Flush the cached permission map (e.g. after a role is updated).
     */
    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
