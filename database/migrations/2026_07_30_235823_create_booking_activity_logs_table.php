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
        Schema::create('booking_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->char('booking_id', 36)->index('idx_booking_activity_booking');
            $table->unsignedInteger('actor_user_id')->nullable();
            $table->enum('actor_role', ['system', 'admin', 'client'])->default('system');
            $table->string('event_type', 50);
            $table->string('action', 191);
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->useCurrent()->index('idx_booking_activity_created');

            $table->foreign('booking_id', 'fk_booking_activity_booking')
                ->references('id')->on('bookings')
                ->onDelete('cascade');

            $table->foreign('actor_user_id', 'fk_booking_activity_actor')
                ->references('id')->on('users')
                ->onDelete('set null');
        });

        // Backfill a baseline log entry for existing bookings.
        $sql = <<<'SQL'
            INSERT INTO booking_activity_logs (booking_id, actor_user_id, actor_role, event_type, action, detail, created_at)
            SELECT
                b.id,
                NULL,
                'system',
                'booking_submitted',
                'Booking submitted',
                CONCAT('Status: ', REPLACE(b.status, '_', ' ')),
                b.created_at
            FROM bookings b
            WHERE NOT EXISTS (
                SELECT 1
                FROM booking_activity_logs l
                WHERE l.booking_id = b.id
                  AND l.event_type = 'booking_submitted'
            )
SQL;
        DB::statement($sql);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_activity_logs');
    }
};
