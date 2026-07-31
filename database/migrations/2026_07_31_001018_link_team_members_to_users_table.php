<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('team_members', 'user_id')) {
            Schema::table('team_members', function (Blueprint $table) {
                $table->unsignedInteger('user_id')->nullable()->after('id')->index('idx_team_members_user_id');

                $table->foreign('user_id', 'fk_team_members_user_id')
                    ->references('id')->on('users')
                    ->onDelete('set null');
            });
        }

        // Link existing team members to users by matching emails
        $teamMembers = DB::table('team_members')->whereNotNull('email')->where('email', '!=', '')->get();
        foreach ($teamMembers as $tm) {
            $user = DB::table('users')->whereRaw('LOWER(TRIM(email)) = LOWER(TRIM(?))', [$tm->email])->first();
            if ($user) {
                DB::table('team_members')->where('id', $tm->id)->update(['user_id' => $user->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropForeign('fk_team_members_user_id');
            $table->dropColumn('user_id');
        });
    }
};
