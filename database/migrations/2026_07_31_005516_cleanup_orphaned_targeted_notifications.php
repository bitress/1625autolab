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
        // Re-backfill user_id from legacy payload marker if available.
        $notifications = DB::table('notifications')
            ->where(function ($q) {
                $q->whereNull('user_id')->orWhere('user_id', 0);
            })
            ->whereNotNull('data')
            ->get();

        foreach ($notifications as $n) {
            $data = json_decode($n->data, true);
            if (is_array($data) && isset($data['_targetUserId'])) {
                $targetUserId = (int) $data['_targetUserId'];
                if ($targetUserId > 0) {
                    DB::table('notifications')->where('id', $n->id)->update(['user_id' => $targetUserId]);
                }
            }
        }

        // Remove targeted notification rows that still cannot be associated with a
        // specific user. These types are never valid broadcasts.
        $targetedTypes = [
            'status_changed',
            'build_update',
            'parts_update',
            'order_created',
            'order_status',
            'order_tracking',
            'slot_available',
            'assignment',
        ];

        $remainingNotifications = DB::table('notifications')
            ->whereIn('type', $targetedTypes)
            ->where(function ($q) {
                $q->whereNull('user_id')->orWhere('user_id', 0);
            })
            ->get();

        foreach ($remainingNotifications as $n) {
            $data = json_decode($n->data, true);
            if (empty($data) || ! is_array($data) || empty($data['_targetUserId']) || (int) $data['_targetUserId'] === 0) {
                DB::table('notifications')->where('id', $n->id)->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-way data cleanup, down is intentionally empty.
    }
};
