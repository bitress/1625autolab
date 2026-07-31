<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * PrivacyService
 *
 * GDPR / Data Privacy tools for client users:
 *   - Export all personal data (profile, bookings, vehicles, reviews) as JSON
 *   - Delete account and wipe all related data
 *
 * Requires migration 060_add_consent_to_users.sql.
 */
class PrivacyService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Export all data for a user as an associative array (caller converts to JSON).
     *
     * @return array<string, mixed>
     */
    public function exportData(int $userId): array
    {
        // Profile
        $profile = (array) DB::table('users')
            ->select('id', 'name', 'email', 'phone', 'role', 'created_at')
            ->where('id', $userId)
            ->first() ?: [];

        // Bookings (own)
        $bookings = DB::table('bookings as b')
            ->leftJoin('services as s', 's.id', '=', 'b.service_id')
            ->select(
                'b.id',
                'b.reference_number',
                's.title as service_name',
                'b.appointment_date',
                'b.appointment_time',
                'b.vehicle_info',
                'b.vehicle_make',
                'b.vehicle_model',
                'b.vehicle_year',
                'b.status',
                'b.notes',
                'b.created_at'
            )
            ->where('b.user_id', $userId)
            ->orderByDesc('b.created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        // Vehicles / Garage
        $vehicles = DB::table('client_vehicles')
            ->select('make', 'model', 'year', 'vin', 'license_plate', 'created_at')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        // Reviews
        $reviews = DB::table('booking_reviews as r')
            ->join('bookings as b', 'b.id', '=', 'r.booking_id')
            ->leftJoin('services as s', 's.id', '=', 'b.service_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->select('u.name as reviewer_name', 's.title as service_name', 'r.rating', 'r.review', 'r.created_at')
            ->where('r.user_id', $userId)
            ->orderByDesc('r.created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        // Waitlist entries
        $waitlist = DB::table('booking_waitlist')
            ->select('slot_date', 'slot_time', 'service_ids', 'status', 'created_at')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        return [
            'exportedAt' => Carbon::now()->toIso8601String(),
            'profile' => $profile,
            'bookings' => $bookings,
            'vehicles' => $vehicles,
            'reviews' => $reviews,
            'waitlist' => $waitlist,
        ];
    }

    /**
     * Permanently delete a user account and all associated personal data.
     *
     * This wipes: bookings (user_id set to NULL, personal fields anonymised),
     * client_vehicles, booking_reviews (soft-anonymised), waitlist entries,
     * notifications, sessions, and finally the user row itself.
     *
     * @param  int  $userId  User to delete.
     * @param  string  $reason  Optional reason for audit log.
     */
    public function deleteAccount(int $userId, string $reason = 'user_request'): void
    {
        DB::beginTransaction();

        try {
            // Anonymise bookings (keep history for business records, strip PII)
            DB::table('bookings')
                ->where('user_id', $userId)
                ->update([
                    'user_id' => null,
                    'name' => '[Deleted User]',
                    'email' => '',
                    'phone' => '',
                    'signature_data' => null,
                    'internal_notes' => null,
                ]);

            // Remove vehicles
            DB::table('client_vehicles')->where('user_id', $userId)->delete();

            // Remove review text while keeping rating/service history.
            // reviewer_name is derived from users.name via JOIN in current schema.
            DB::table('booking_reviews')
                ->where('user_id', $userId)
                ->update(['review' => null]);

            // Remove waitlist entries
            DB::table('booking_waitlist')->where('user_id', $userId)->delete();

            // Remove notifications
            DB::table('notifications')->where('user_id', $userId)->delete();

            // Revoke auth sessions
            DB::table('auth_sessions')
                ->where('user_id', $userId)
                ->update([
                    'revoked_at' => Carbon::now(),
                    'revoked_reason' => $reason,
                ]);

            // Delete the user row itself
            DB::table('users')->where('id', $userId)->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw new RuntimeException('Failed to delete account: '.$e->getMessage(), 500, $e);
        }
    }

    /**
     * Record consent for a user (called at registration or when consent version changes).
     */
    public function recordConsent(int $userId, string $version = '1.0'): void
    {
        DB::table('users')
            ->where('id', $userId)
            ->update([
                'consented_at' => Carbon::now(),
                'consent_version' => $version,
            ]);
    }
}
