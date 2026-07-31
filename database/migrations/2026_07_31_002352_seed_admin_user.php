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
        DB::table('users')->upsert([
            [
                'name' => 'Admin',
                'email' => 'admin@1625autolab.com',
                'phone' => '',
                'password' => '$argon2id$v=19$m=65536,t=4,p=1$YUVqV29qZDEyWUs2RGg3Ug$OD0Az+nnjufFjjq4FeU56RaUnKUNbvrFOQ2/CjUE7Bw',
                'role' => 'admin',
            ],
        ], ['email'], ['name', 'phone', 'password', 'role']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('email', 'admin@1625autolab.com')->delete();
    }
};
