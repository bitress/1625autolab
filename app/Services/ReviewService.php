<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Manages booking reviews submitted by clients after a completed service.
 *
 * Reviews are gated by admin approval before being surfaced publicly.
 */
class ReviewService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Client actions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create or replace a review for a completed booking.
     *
     * A client may re-submit their review (it replaces the previous one and
     * resets approval so the admin can re-moderate).
     *
     * @param  array<string, mixed>  $data  Must contain 'rating' (1-5) and optionally 'review'
     * @return array<string, mixed>
     *
     * @throws RuntimeException 422 on validation failure
     */
    public function create(string $bookingId, int $userId, array $data): array
    {
        $rating = (int) ($data['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            throw new RuntimeException('Rating must be between 1 and 5.', 422);
        }
        $review = isset($data['review']) ? mb_substr(trim((string) $data['review']), 0, 2000) : null;

        DB::table('booking_reviews')->updateOrInsert(
            ['booking_id' => $bookingId],
            [
                'user_id' => $userId,
                'rating' => $rating,
                'review' => $review,
                'is_approved' => 0,
                'updated_at' => Carbon::now(),
            ]
        );

        $created = $this->getForBooking($bookingId);

        if (! $created) {
            throw new RuntimeException('Failed to retrieve the created review.', 500);
        }

        return $created;
    }

    /**
     * Return the review for a specific booking (or null if none exists yet).
     *
     * @return array<string, mixed>|null
     */
    public function getForBooking(string $bookingId): ?array
    {
        $row = clone $this->baseQuery()
            ->where('r.booking_id', $bookingId)
            ->first();

        return $row ? $this->format((array) $row) : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin actions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return all reviews with their booking and reviewer info.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $rows = clone $this->baseQuery()
            ->orderBy('r.created_at', 'desc')
            ->get();

        return $rows->map(fn ($row) => $this->format((array) $row))->toArray();
    }

    /**
     * Return all approved (published) reviews, optionally filtered by service.
     *
     * @param  int|null  $serviceId  When provided, only reviews for that service are returned.
     * @return array<int, array<string, mixed>>
     */
    public function getPublished(?int $serviceId = null): array
    {
        $query = clone $this->baseQuery()
            ->where('r.is_approved', 1)
            ->orderBy('r.created_at', 'desc');

        if ($serviceId !== null) {
            $query->where('b.service_id', $serviceId);
        }

        return $query->get()->map(fn ($row) => $this->format((array) $row))->toArray();
    }

    /** Approve a review. */
    public function approve(int $id): void
    {
        DB::table('booking_reviews')
            ->where('id', $id)
            ->update([
                'is_approved' => 1,
                'updated_at' => Carbon::now(),
            ]);
    }

    /** Reject (un-approve) a review. */
    public function reject(int $id): void
    {
        DB::table('booking_reviews')
            ->where('id', $id)
            ->update([
                'is_approved' => 0,
                'updated_at' => Carbon::now(),
            ]);
    }

    /** Delete a review permanently. */
    public function delete(int $id): void
    {
        DB::table('booking_reviews')
            ->where('id', $id)
            ->delete();
    }

    /**
     * Return total/average rating stats.
     *
     * @return array{total: int, avgRating: float}
     */
    public function getStats(): array
    {
        $stats = DB::table('booking_reviews')
            ->where('is_approved', 1)
            ->select(
                DB::raw('COUNT(*) AS total'),
                DB::raw('COALESCE(AVG(rating), 0) AS avg_rating')
            )
            ->first();

        return [
            'total' => (int) ($stats->total ?? 0),
            'avgRating' => (float) ($stats->avg_rating ?? 0),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function baseQuery()
    {
        return DB::table('booking_reviews as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->join('bookings as b', 'b.id', '=', 'r.booking_id')
            ->leftJoin('services as s', 's.id', '=', 'b.service_id')
            ->select(
                'r.*',
                'u.name as reviewer_name',
                's.title as service_name',
                'b.vehicle_info'
            );
    }

    /** @param array<string, mixed> $row */
    private function format(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'bookingId' => (string) $row['booking_id'],
            'userId' => (int) $row['user_id'],
            'reviewerName' => (string) ($row['reviewer_name'] ?? ''),
            'serviceName' => (string) ($row['service_name'] ?? ''),
            'vehicleInfo' => (string) ($row['vehicle_info'] ?? ''),
            'rating' => (int) $row['rating'],
            'review' => $row['review'] !== null ? (string) $row['review'] : null,
            'isApproved' => (bool) $row['is_approved'],
            'createdAt' => (string) $row['created_at'],
            'updatedAt' => (string) $row['updated_at'],
        ];
    }
}
