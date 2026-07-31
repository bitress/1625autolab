<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Build-update records (progress photos & notes) attached to a booking.
 */
class BuildUpdateService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return all build updates for a booking, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByBookingId(string $bookingId): array
    {
        $rows = DB::table('build_updates')
            ->where('booking_id', $bookingId)
            ->orderBy('created_at', 'asc')
            ->get();

        return $rows->map(fn ($row) => $this->formatRow((array) $row))->toArray();
    }

    /**
     * Create a new build update.
     *
     * @param  array<int, string>  $photoUrls
     * @return array<string, mixed>
     */
    public function create(string $bookingId, string $note, array $photoUrls): array
    {
        $note = trim($note);
        $urlsJson = json_encode(array_values($photoUrls), JSON_UNESCAPED_UNICODE);

        $id = DB::table('build_updates')->insertGetId([
            'booking_id' => $bookingId,
            'note' => $note !== '' ? $note : null,
            'photo_urls' => $urlsJson,
            'created_at' => Carbon::now(),
        ]);

        $row = DB::table('build_updates')->where('id', $id)->first();

        if (! $row) {
            throw new RuntimeException('Failed to retrieve created build update.', 500);
        }

        $update = $this->formatRow((array) $row);

        $this->logBuildUpdateActivity($update);

        return $update;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $row */
    private function formatRow(array $row): array
    {
        $urls = [];
        if (! empty($row['photo_urls'])) {
            $decoded = json_decode((string) $row['photo_urls'], true);
            if (is_array($decoded)) {
                $urls = $decoded;
            }
        }

        return [
            'id' => (int) $row['id'],
            'bookingId' => (string) $row['booking_id'],
            'note' => (string) ($row['note'] ?? ''),
            'photoUrls' => $urls,
            'createdAt' => (string) $row['created_at'],
        ];
    }

    /** @param array<string, mixed> $update */
    private function logBuildUpdateActivity(array $update): void
    {
        try {
            if (function_exists('activity')) {
                $subjectId = (string) ((int) ($update['id'] ?? 0));
                $bookingId = (string) ($update['bookingId'] ?? '');
                $photoCount = is_array($update['photoUrls'] ?? null) ? count($update['photoUrls']) : 0;

                $logger = activity()
                    ->forSubject('build_updates', $subjectId)
                    ->withProperties([
                        'bookingId' => $bookingId,
                        'photoCount' => $photoCount,
                        'note' => (string) ($update['note'] ?? ''),
                        'createdAt' => (string) ($update['createdAt'] ?? ''),
                    ]);

                $actorUserId = $this->resolveActorUserId();
                if ($actorUserId !== null && $actorUserId > 0) {
                    $logger->byUser($actorUserId);
                }

                $logger->log('BUILD_UPDATE_CREATED', 'build_updates');
            }
        } catch (\Throwable $e) {
            error_log('[BuildUpdateService] Failed to write build update activity log: '.$e->getMessage());
        }
    }

    private function resolveActorUserId(): ?int
    {
        try {
            $user = Auth::user();

            return $user ? (int) $user->id : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
