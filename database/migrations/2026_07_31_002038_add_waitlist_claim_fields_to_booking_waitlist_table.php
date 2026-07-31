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
        Schema::table('booking_waitlist', function (Blueprint $table) {
            $table->char('claim_token', 64)->nullable()->after('notified_at')->index('idx_bwl_claim_token');
            $table->timestamp('claim_expires_at')->nullable()->default(null)->after('claim_token')->index('idx_bwl_claim_expiry');
            $table->timestamp('claimed_at')->nullable()->default(null)->after('claim_expires_at');
            $table->char('booked_booking_id', 36)->nullable()->after('claimed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_waitlist', function (Blueprint $table) {
            $table->dropIndex('idx_bwl_claim_token');
            $table->dropIndex('idx_bwl_claim_expiry');
            $table->dropColumn([
                'claim_token',
                'claim_expires_at',
                'claimed_at',
                'booked_booking_id',
            ]);
        });
    }
};
