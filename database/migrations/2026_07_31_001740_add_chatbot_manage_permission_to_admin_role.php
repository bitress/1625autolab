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
        $roles = DB::table('roles')->whereIn('role_key', ['admin'])->get();
        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions_json, true) ?? [];
            if (is_array($permissions) && !in_array('chatbot:manage', $permissions)) {
                $permissions[] = 'chatbot:manage';
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
        // Down migration left empty as removing an item from a JSON array is complex in standard MySQL.
    }
};
