<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BookingAccessService
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly SiteSettingsService $siteSettings,
        private readonly BookingService $bookings,
    ) {}

    /**
     * Whether the authenticated user has a staff role.
     *
     * @param  array<string, mixed>  $payload
     */
    public function isStaffRole(array $payload): bool
    {
        return strtolower(trim((string) ($payload['role'] ?? ''))) === 'staff';
    }

    /**
     * Whether staff members are allowed to view all bookings (site setting flag).
     */
    public function staffCanViewAllBookings(): bool
    {
        return $this->getSiteSettingFlag('staff_can_view_all_bookings', false);
    }

    /**
     * Whether staff members are allowed to manage all bookings (site setting flag).
     */
    public function staffCanManageAllBookings(): bool
    {
        return $this->getSiteSettingFlag('staff_can_manage_all_bookings', false);
    }

    /**
     * Whether a booking is assigned to a given user (by their team-member userId).
     *
     * @param  array<string, mixed>  $booking
     */
    public function bookingAssignedToUser(array $booking, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $assignedTech = $booking['assignedTech'] ?? null;
        if (! is_array($assignedTech)) {
            return false;
        }

        $assignedUserId = isset($assignedTech['userId']) && $assignedTech['userId'] !== null
            ? (int) $assignedTech['userId']
            : 0;

        return $assignedUserId > 0 && $assignedUserId === $userId;
    }

    /**
     * Return all bookings visible to the authenticated user.
     * Staff are limited to assigned bookings unless the site setting allows all.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function getAccessibleBookingsForPayload(array $payload): array
    {
        $bookings = $this->bookings->getAll();

        if (! $this->isStaffRole($payload) || $this->staffCanViewAllBookings()) {
            return $bookings;
        }

        $userId = (int) ($payload['sub'] ?? 0);

        return array_values(array_filter(
            $bookings,
            fn (array $booking): bool => $this->bookingAssignedToUser($booking, $userId)
        ));
    }

    /**
     * Ensure the authenticated user can VIEW a specific booking.
     * Throws 403/404 if not allowed.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function requireBookingVisibilityForPayload(array $payload, string $bookingId): array
    {
        $booking = $this->bookings->adminFindById($bookingId);
        if ($booking === null) {
            abort(404, 'Booking not found.');
        }

        if (! $this->isStaffRole($payload) || $this->staffCanViewAllBookings()) {
            return $booking;
        }

        if ($this->bookingAssignedToUser($booking, (int) ($payload['sub'] ?? 0))) {
            return $booking;
        }

        abort(403, 'Forbidden. Staff can only view assigned bookings.');
    }

    /**
     * Ensure the authenticated user can MUTATE a specific booking.
     * Throws 403/404 if not allowed.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function requireBookingMutationForPayload(array $payload, string $bookingId): array
    {
        $booking = $this->bookings->adminFindById($bookingId);
        if ($booking === null) {
            abort(404, 'Booking not found.');
        }

        if (! $this->isStaffRole($payload) || $this->staffCanManageAllBookings()) {
            return $booking;
        }

        if ($this->bookingAssignedToUser($booking, (int) ($payload['sub'] ?? 0))) {
            return $booking;
        }

        abort(403, 'Forbidden. Staff can only manage assigned bookings.');
    }

    /**
     * Read a boolean site setting, defaulting to $default.
     */
    private function getSiteSettingFlag(string $key, bool $default = false): bool
    {
        try {
            $settings = $this->siteSettings->getAll();
            $raw = strtolower(trim((string) ($settings[$key] ?? ($default ? '1' : '0'))));

            return in_array($raw, ['1', 'true', 'yes', 'on'], true);
        } catch (\Throwable $e) {
            Log::debug('BookingAccessService: could not read site setting.', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return $default;
        }
    }
}
