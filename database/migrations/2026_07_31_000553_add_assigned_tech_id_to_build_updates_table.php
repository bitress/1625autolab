<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('build_updates', function (Blueprint $table) {
            $table->unsignedInteger('assigned_tech_id')->nullable()->after('booking_id')->index('idx_build_updates_assigned_tech_id');

            $table->foreign('assigned_tech_id', 'fk_build_updates_assigned_tech')
                ->references('id')->on('team_members')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('build_updates', function (Blueprint $table) {
            $table->dropForeign('fk_build_updates_assigned_tech');
            $table->dropColumn('assigned_tech_id');
        });
    }
};
