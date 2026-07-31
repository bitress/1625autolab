<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * WaitlistService
 *
 * Manages the booking_waitlist table so customers can be auto-notified when a
 * fully-booked time slot becomes available due to a cancellation.
 */
class WaitlistService
{
    private int $claimTtlMinutes;

    public function __construct()
    {
        $this->claimTtlMinutes = defined('WAITLIST_CLAIM_TTL_MINUTES')
            ? max(5, (int) WAITLIST_CLAIM_TTL_MINUTES)
            : 30;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Add a customer to the waitlist for a specific slot.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function join(array $data): array
    {
        $this->validateJoin($data);

        $slotDate = trim((string) ($data['slotDate'] ?? ''));
        $slotTime = trim((string) ($data['slotTime'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        // Prevent duplicate waiting entries for same person+slot
        $exists = DB::table('booking_waitlist')
            ->where('slot_date', $slotDate)
            ->where('slot_time', $slotTime)
            ->where('email', $email)
            ->where('status', 'waiting')
            ->exists();

        if ($exists) {
            throw new RuntimeException('You are already on the waitlist for this slot.', 409);
        }

        $id = DB::table('booking_waitlist')->insertGetId([
            'slot_date' => $slotDate,
            'slot_time' => $slotTime,
            'user_id' => isset($data['userId']) ? (int) $data['userId'] : null,
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => $email,
            'phone' => trim((string) ($data['phone'] ?? '')),
            'service_ids' => trim((string) ($data['serviceIds'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'status' => 'waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entry = $this->dbGetById($id);

        $this->logWaitlistActivity('WAITLIST_JOINED', $entry, [
            'status' => (string) ($entry['status'] ?? 'waiting'),
        ]);

        return $entry;
    }

    /**
     * List all waiting entries (admin).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(string $status = ''): array
    {
        $this->expireStaleClaims();

        $query = DB::table('booking_waitlist as w')
            ->leftJoin('users as u', 'u.id', '=', 'w.user_id')
            ->select('w.*', 'u.name as user_name', 'u.email as user_email')
            ->orderBy('w.slot_date', 'asc')
            ->orderBy('w.slot_time', 'asc')
            ->orderBy('w.created_at', 'asc');

        if ($status !== '') {
            $query->where('w.status', $status);
        }

        $rows = $query->get();

        return $rows->map(fn ($row) => $this->mapRow((array) $row))->toArray();
    }

    /**
     * Get waitlist entries for a specific slot (for checking if anyone is waiting).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getForSlot(string $date, string $time, string $status = 'waiting'): array
    {
        $this->expireStaleClaims();

        $rows = DB::table('booking_waitlist')
            ->where('slot_date', $date)
            ->where(function ($query) use ($time) {
                $query->where('slot_time', $time)
                    ->orWhere('slot_time', 'any');
            })
            ->where('status', $status)
            ->orderBy('created_at', 'asc')
            ->get();

        return $rows->map(fn ($row) => $this->mapRow((array) $row))->toArray();
    }

    /**
     * Resolve and validate a claim token.
     *
     * @return array<string, mixed>
     */
    public function getClaimByToken(string $token): array
    {
        $clean = trim($token);
        if ($clean === '') {
            throw new RuntimeException('Claim token is required.', 422);
        }

        $this->expireStaleClaims();

        $row = DB::table('booking_waitlist')->where('claim_token', $clean)->first();

        if (! $row) {
            throw new RuntimeException('Claim link is invalid or has expired.', 404);
        }

        $entry = $this->mapRow((array) $row);

        if (($entry['status'] ?? '') === 'booked') {
            throw new RuntimeException('This claim link has already been used.', 409);
        }
        if (($entry['status'] ?? '') === 'expired') {
            throw new RuntimeException('This claim link has expired.', 409);
        }
        if (($entry['status'] ?? '') !== 'notified') {
            throw new RuntimeException('This waitlist entry is not claimable.', 409);
        }

        $expiresAt = (string) ($entry['claimExpiresAt'] ?? '');
        if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
            $this->markExpired((int) $entry['id']);
            throw new RuntimeException('This claim link has expired.', 409);
        }

        return $entry;
    }

    /**
     * Validate a claim token against booking data.
     *
     * @param  array<string, mixed>  $bookingData
     * @return array<string, mixed>
     */
    public function validateClaimForBooking(string $token, array $bookingData): array
    {
        $entry = $this->getClaimByToken($token);

        $date = trim((string) ($bookingData['appointmentDate'] ?? ''));
        $time = trim((string) ($bookingData['appointmentTime'] ?? ''));
        $email = strtolower(trim((string) ($bookingData['email'] ?? '')));

        if ($date !== (string) ($entry['slotDate'] ?? '')) {
            throw new RuntimeException('Claim token date does not match the selected appointment date.', 409);
        }

        $entrySlotTime = (string) ($entry['slotTime'] ?? '');
        if ($entrySlotTime !== 'any' && $time !== $entrySlotTime) {
            throw new RuntimeException('Claim token time does not match the selected appointment time.', 409);
        }

        $entryEmail = strtolower((string) ($entry['email'] ?? ''));
        if ($entryEmail !== '' && $email !== '' && $entryEmail !== $email) {
            throw new RuntimeException('Claim token email does not match this booking email.', 409);
        }

        return $entry;
    }

    public function markBookedByClaimToken(string $token, string $bookingId): void
    {
        $affected = DB::table('booking_waitlist')
            ->where('claim_token', trim($token))
            ->where('status', 'notified')
            ->update([
                'status' => 'booked',
                'claimed_at' => now(),
                'booked_booking_id' => $bookingId,
                'updated_at' => now(),
            ]);

        if ($affected > 0) {
            $entry = $this->findByClaimToken(trim($token));
            if ($entry !== null) {
                $this->logWaitlistActivity('WAITLIST_CLAIM_BOOKED', $entry, [
                    'bookingId' => $bookingId,
                ]);
            }
        }
    }

    /** Remove a waitlist entry (admin or self). */
    public function remove(int $id, ?int $requestingUserId = null): void
    {
        $entry = $this->dbGetById($id);

        if ($requestingUserId !== null && (int) ($entry['userId'] ?? 0) !== $requestingUserId) {
            throw new RuntimeException('Not authorized to remove this waitlist entry.', 403);
        }

        $affected = DB::table('booking_waitlist')->where('id', $id)->delete();

        if ($affected === 0) {
            throw new RuntimeException('Waitlist entry not found.', 404);
        }

        $this->logWaitlistActivity('WAITLIST_REMOVED', $entry, [
            'removedByUserId' => $requestingUserId,
        ]);
    }

    /**
     * Mark a waiting entry as 'notified' and fire notification channels.
     * Called automatically when a booking slot opens (cancellation).
     *
     * @param  array<string, mixed>  $entry  Row from booking_waitlist
     */
    public function notifyEntry(array $entry): void
    {
        $id = (int) ($entry['id'] ?? 0);
        $date = (string) ($entry['slotDate'] ?? '');
        $time = (string) ($entry['slotTime'] ?? '');
        $name = (string) ($entry['name'] ?? 'there');
        $email = (string) ($entry['email'] ?? '');
        $phone = (string) ($entry['phone'] ?? '');
        $claimToken = $this->createClaimToken();
        $claimExpiresAt = date('Y-m-d H:i:s', time() + ($this->claimTtlMinutes * 60));
        $claimUrl = $this->buildClaimUrl($claimToken);

        // Mark as notified immediately to prevent duplicate sends
        DB::table('booking_waitlist')
            ->where('id', $id)
            ->update([
                'status' => 'notified',
                'notified_at' => now(),
                'claim_token' => $claimToken,
                'claim_expires_at' => $claimExpiresAt,
                'claimed_at' => null,
                'booked_booking_id' => null,
                'updated_at' => now(),
            ]);

        $this->logWaitlistActivity('WAITLIST_NOTIFIED', $entry, [
            'claimExpiresAt' => $claimExpiresAt,
            'claimWindowMinutes' => $this->claimTtlMinutes,
        ]);

        $userId = isset($entry['userId']) && $entry['userId'] ? (int) $entry['userId'] : 0;

        try {
            (new NotificationJobQueueService)->dispatch('waitlist_slot_available', [
                'userId' => $userId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'date' => $date,
                'time' => $time,
                'claimUrl' => $claimUrl,
                'claimWindowMinutes' => $this->claimTtlMinutes,
            ]);
        } catch (Throwable) {
            // fail silently
        }
    }

    /**
     * When a booking is cancelled, notify the first person on the waitlist
     * for that slot (if any).
     */
    public function handleBookingCancelled(string $slotDate, string $slotTime): void
    {
        $this->expireStaleClaims();
        $waiting = $this->getForSlot($slotDate, $slotTime, 'waiting');
        if (empty($waiting)) {
            return;
        }
        // Notify only the first person; others remain waiting
        $this->notifyEntry($waiting[0]);
    }

    /**
     * Auto-fill processor:
     * - expires stale claim links,
     * - for each waitlist slot with no active claim and available capacity,
     *   notifies the next waiting user.
     *
     * @return array<string, int>
     */
    public function processAutoFill(): array
    {
        $this->expireStaleClaims();

        $rows = DB::table('booking_waitlist')
            ->select('slot_date', 'slot_time')
            ->whereIn('status', ['waiting', 'notified'])
            ->distinct()
            ->orderBy('slot_date', 'asc')
            ->orderBy('slot_time', 'asc')
            ->get();

        $slotsChecked = 0;
        $notified = 0;

        foreach ($rows as $row) {
            $rowArray = (array) $row;
            $slotDate = (string) ($rowArray['slot_date'] ?? '');
            $slotTime = (string) ($rowArray['slot_time'] ?? '');

            if ($slotDate === '' || $slotTime === '') {
                continue;
            }

            $slotsChecked++;

            if ($this->hasActiveNotifiedClaim($slotDate, $slotTime)) {
                continue;
            }

            if (! $this->isSlotAvailableForWaitlist($slotDate, $slotTime)) {
                continue;
            }

            $waiting = $this->getForSlot($slotDate, $slotTime, 'waiting');
            if (empty($waiting)) {
                continue;
            }

            $this->notifyEntry($waiting[0]);
            $notified++;
        }

        return [
            'slotsChecked' => $slotsChecked,
            'notified' => $notified,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function dbGetById(int $id): array
    {
        $row = DB::table('booking_waitlist')->where('id', $id)->first();
        if (! $row) {
            throw new RuntimeException('Waitlist entry not found.', 404);
        }

        return $this->mapRow((array) $row);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'slotDate' => (string) ($row['slot_date'] ?? ''),
            'slotTime' => (string) ($row['slot_time'] ?? ''),
            'userId' => isset($row['user_id']) && $row['user_id'] !== null
                               ? (int) $row['user_id'] : null,
            'name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'serviceIds' => (string) ($row['service_ids'] ?? ''),
            'notes' => isset($row['notes']) ? (string) $row['notes'] : null,
            'status' => (string) ($row['status'] ?? 'waiting'),
            'notifiedAt' => isset($row['notified_at']) ? (string) $row['notified_at'] : null,
            'claimToken' => isset($row['claim_token']) ? (string) $row['claim_token'] : null,
            'claimExpiresAt' => isset($row['claim_expires_at']) ? (string) $row['claim_expires_at'] : null,
            'claimedAt' => isset($row['claimed_at']) ? (string) $row['claimed_at'] : null,
            'bookedBookingId' => isset($row['booked_booking_id']) ? (string) $row['booked_booking_id'] : null,
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),

            // Joined fields
            'userName' => isset($row['user_name']) ? (string) $row['user_name'] : null,
            'userEmail' => isset($row['user_email']) ? (string) $row['user_email'] : null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function validateJoin(array $data): void
    {
        $required = ['slotDate', 'slotTime', 'name', 'email'];
        foreach ($required as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw new RuntimeException("Field \"{$field}\" is required.", 422);
            }
        }
        if (! filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email address.', 422);
        }
    }

    private function expireStaleClaims(): void
    {
        DB::table('booking_waitlist')
            ->where('status', 'notified')
            ->whereNotNull('claim_expires_at')
            ->where('claim_expires_at', '<', now())
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);
    }

    private function hasActiveNotifiedClaim(string $slotDate, string $slotTime): bool
    {
        return DB::table('booking_waitlist')
            ->where('slot_date', $slotDate)
            ->where('slot_time', $slotTime)
            ->where('status', 'notified')
            ->where(function ($query) {
                $query->whereNull('claim_expires_at')
                    ->orWhere('claim_expires_at', '>=', now());
            })
            ->exists();
    }

    private function isSlotAvailableForWaitlist(string $slotDate, string $slotTime): bool
    {
        $bookingSvc = new BookingService;
        $capacity = max(1, $bookingSvc->getSlotCapacity());

        // "any" means the customer accepts any slot for the date.
        if ($slotTime === 'any') {
            $counts = $bookingSvc->getSlotCounts($slotDate);
            foreach ($counts as $count) {
                if ((int) $count < $capacity) {
                    return true;
                }
            }

            // If no active bookings are recorded yet, the date is considered available.
            return empty($counts);
        }

        $counts = $bookingSvc->getSlotCounts($slotDate);
        $activeForSlot = (int) ($counts[$slotTime] ?? 0);

        return $activeForSlot < $capacity;
    }

    private function markExpired(int $id): void
    {
        $entry = $this->dbGetById($id);

        $affected = DB::table('booking_waitlist')
            ->where('id', $id)
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);

        if ($affected > 0) {
            $this->logWaitlistActivity('WAITLIST_EXPIRED', $entry);
        }
    }

    private function createClaimToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function buildClaimUrl(string $claimToken): string
    {
        $baseUrl = rtrim((defined('APP_URL') ? APP_URL : config('app.url', '')), '/');

        return $baseUrl.'/booking?waitlist_claim='.urlencode($claimToken);
    }

    /** @return array<string, mixed>|null */
    private function findByClaimToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $row = DB::table('booking_waitlist')->where('claim_token', $token)->first();

        return $row ? $this->mapRow((array) $row) : null;
    }

    /** @param array<string, mixed> $entry @param array<string, mixed> $properties */
    private function logWaitlistActivity(string $description, array $entry, array $properties = []): void
    {
        try {
            if (! function_exists('activity')) {
                return;
            }

            $subjectId = (string) ((int) ($entry['id'] ?? 0));
            $logger = activity()
                ->forSubject('booking_waitlist', $subjectId)
                ->withProperties($properties + [
                    'slotDate' => (string) ($entry['slotDate'] ?? ''),
                    'slotTime' => (string) ($entry['slotTime'] ?? ''),
                    'status' => (string) ($entry['status'] ?? ''),
                    'bookedBookingId' => (string) ($entry['bookedBookingId'] ?? ''),
                ]);

            $actorUserId = $this->resolveActorUserId();
            if ($actorUserId === null && isset($entry['userId']) && $entry['userId'] !== null) {
                $actorUserId = (int) $entry['userId'];
            }
            if ($actorUserId !== null && $actorUserId > 0) {
                $logger->byUser($actorUserId);
            }

            $logger->log($description, 'booking_waitlist');
        } catch (Throwable $e) {
            error_log('[WaitlistService] Failed to write activity log: '.$e->getMessage());
        }
    }

    private function resolveActorUserId(): ?int
    {
        try {
            $payload = Auth::user();

            return $payload ? (int) $payload->id : null;
        } catch (Throwable) {
            return null;
        }
    }
}
