<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('roles')->upsert([
            [
                'role_key' => 'admin',
                'name' => 'Admin',
                'description' => 'Full control over admin panel and user access.',
                'permissions_json' => json_encode([
                    'analytics:view', 'bookings:manage', 'bookings:assign-tech', 'bookings:notes',
                    'build-updates:manage', 'clients:manage', 'users:manage', 'roles:view',
                    'roles:manage', 'reviews:manage', 'shop-hours:manage', 'media:upload',
                ]),
                'is_system' => true,
            ],
            [
                'role_key' => 'manager',
                'name' => 'Manager',
                'description' => 'Operations and analytics access without user or role administration.',
                'permissions_json' => json_encode([
                    'analytics:view', 'bookings:manage', 'bookings:assign-tech', 'bookings:notes',
                    'build-updates:manage', 'clients:manage', 'roles:view', 'reviews:manage',
                ]),
                'is_system' => true,
            ],
            [
                'role_key' => 'staff',
                'name' => 'Staff',
                'description' => 'Day-to-day booking and client operations access.',
                'permissions_json' => json_encode([
                    'bookings:manage', 'build-updates:manage', 'clients:manage', 'roles:view',
                ]),
                'is_system' => true,
            ],
            [
                'role_key' => 'client',
                'name' => 'Client',
                'description' => 'Client portal access for own account only.',
                'permissions_json' => json_encode(['client:self']),
                'is_system' => true,
            ],
        ], ['role_key'], ['name', 'description', 'permissions_json', 'is_system']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down needed
    }
};
