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
        $roles = DB::table('roles')->whereIn('role_key', ['admin', 'manager'])->get();
        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions_json, true) ?? [];
            if (is_array($permissions) && ! in_array('security:audit:view', $permissions)) {
                $permissions[] = 'security:audit:view';
                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['permissions_json' => json_encode($permissions)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Down migration left empty. Removing a specific value from a JSON array in MySQL
        // typically requires JSON_REMOVE with a dynamically resolved path/index, which is complex.
    }
};
