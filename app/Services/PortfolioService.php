<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PortfolioService
 *
 * Full CRUD for the portfolio table.
 *
 * Public-facing endpoints return only active items (is_active = 1).
 * Admin endpoints return all items.
 */
class PortfolioService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * All portfolio items ordered by sort_order, id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(bool $includeInactive = false): array
    {
        $query = DB::table('portfolio')->orderBy('sort_order', 'asc')->orderBy('id', 'asc');

        if (! $includeInactive) {
            $query->where('is_active', 1);
        }

        return $query->get()->map(fn ($row) => $this->mapRow((array) $row))->toArray();
    }

    /**
     * Single portfolio item by ID.
     *
     * @return array<string, mixed>
     */
    public function getById(int $id, bool $requireActive = true): array
    {
        $query = DB::table('portfolio')->where('id', $id);

        if ($requireActive) {
            $query->where('is_active', 1);
        }

        $row = $query->first();

        if (! $row) {
            throw new RuntimeException('Portfolio item not found.', 404);
        }

        return $this->mapRow((array) $row);
    }

    /**
     * Get a portfolio/build item by slug (public, active only)
     *
     * @return array<string, mixed>
     */
    public function getBySlug(string $slug): array
    {
        $needle = $this->normalizeSlug($slug);

        if ($needle === '') {
            throw new RuntimeException('Portfolio item not found.', 404);
        }

        $row = DB::table('portfolio')
            ->where('slug', $needle)
            ->where('is_active', 1)
            ->first();

        if ($row) {
            return $this->mapRow((array) $row);
        }

        // Fallback to searching among active completed bookings.
        $bookingFallback = $this->findCompletedBookingBuildBySlug($needle);

        if ($bookingFallback !== null) {
            return $bookingFallback;
        }

        throw new RuntimeException('Portfolio item not found.', 404);
    }

    /**
     * Create a new portfolio item. Returns the created record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $this->validatePayload($data);

        $params = $this->bindParams($data);
        $params['created_at'] = Carbon::now();
        $params['updated_at'] = Carbon::now();

        $id = DB::table('portfolio')->insertGetId($params);

        return $this->getById($id, false);
    }

    /**
     * Update an existing portfolio item. Returns the updated record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        $current = $this->getById($id, false);

        $merged = array_merge([
            'title' => $current['title'],
            'slug' => $current['slug'] ?? '',
            'category' => $current['category'],
            'description' => $current['description'],
            'imageUrl' => $current['imageUrl'],
            'images' => $current['images'],
            'sortOrder' => $current['sortOrder'],
            'isActive' => $current['isActive'],
        ], $data);

        $params = $this->bindParams($merged);
        $params['updated_at'] = Carbon::now();

        DB::table('portfolio')->where('id', $id)->update($params);

        $oldUrls = $this->collectPortfolioImageUrls($current);
        $updated = $this->getById($id, false);
        $newUrls = $this->collectPortfolioImageUrls($updated);

        $this->deleteRemovedImageUrls($oldUrls, $newUrls);

        return $updated;
    }

    /**
     * Hard-delete a portfolio item.
     */
    public function delete(int $id): void
    {
        $current = $this->getById($id, false);

        $deleted = DB::table('portfolio')->where('id', $id)->delete();

        if ($deleted === 0) {
            throw new RuntimeException('Portfolio item not found.', 404);
        }

        $this->deleteRemovedImageUrls($this->collectPortfolioImageUrls($current), []);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Map a DB snake_case row to the camelCase API shape.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        $rawImages = $row['images'] ?? '[]';
        $images = json_decode((string) $rawImages, true);

        if (! is_array($images)) {
            $images = [];
        }

        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'slug' => trim((string) ($row['slug'] ?? '')) !== ''
                ? (string) $row['slug']
                : $this->makeSlug((string) ($row['title'] ?? '')),
            'category' => $row['category'],
            'description' => $row['description'],
            'imageUrl' => $row['image_url'],
            'images' => $images,
            'sortOrder' => (int) $row['sort_order'],
            'isActive' => (bool) $row['is_active'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    /** @return array<string, mixed> */
    private function bindParams(array $data): array
    {
        $images = $data['images'] ?? [];
        if (is_string($images)) {
            $decoded = json_decode($images, true);
            $images = is_array($decoded) ? $decoded : [];
        }

        $title = trim((string) ($data['title'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->makeSlug($title);
        }

        // Derive image_url from first image in the array when available.
        $imageUrl = $data['imageUrl'] ?? ($data['image_url'] ?? '');
        if (empty($imageUrl) && ! empty($images)) {
            $imageUrl = $images[0];
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'category' => $data['category'] ?? '',
            'description' => $data['description'] ?? '',
            'image_url   ' => $imageUrl,
            'images' => json_encode(array_values($images), JSON_UNESCAPED_UNICODE),
            'sort_order' => (int) ($data['sortOrder'] ?? ($data['sort_order'] ?? 0)),
            'is_active' => (int) ($data['isActive'] ?? ($data['is_active'] ?? 1)),
        ];
    }

    /** @param array<string, mixed> $data */
    private function validatePayload(array $data): void
    {
        if (empty(trim($data['title'] ?? ''))) {
            throw new RuntimeException('Portfolio item title is required.', 422);
        }
    }

    /** Convert a title to a URL-safe slug. */
    private function makeSlug(string $title): string
    {
        return Str::slug($title);
    }

    private function normalizeSlug(string $slug): string
    {
        return $this->makeSlug(trim($slug));
    }

    /** @param array<string, mixed> $item @return string[] */
    private function collectPortfolioImageUrls(array $item): array
    {
        $urls = [];

        $imageUrl = trim((string) ($item['imageUrl'] ?? ($item['image_url'] ?? '')));
        if ($imageUrl !== '') {
            $urls[] = $imageUrl;
        }

        $images = $item['images'] ?? [];
        if (! is_array($images)) {
            $images = [];
        }
        foreach ($images as $url) {
            if (! is_string($url)) {
                continue;
            }
            $trimmed = trim($url);
            if ($trimmed !== '') {
                $urls[] = $trimmed;
            }
        }

        return array_values(array_unique($urls));
    }

    /** @param string[] $oldUrls @param string[] $newUrls */
    private function deleteRemovedImageUrls(array $oldUrls, array $newUrls): void
    {
        $toDelete = array_diff($oldUrls, $newUrls);
        if (empty($toDelete)) {
            return;
        }

        if (class_exists(UploadStorage::class)) {
            $storage = app(UploadStorage::class);
            foreach ($toDelete as $url) {
                try {
                    if (method_exists($storage, 'deleteByUrl')) {
                        $storage->deleteByUrl($url);
                    }
                } catch (\Throwable) {
                    // Keep CRUD successful even if storage cleanup fails.
                }
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function findCompletedBookingBuildBySlug(string $slug): ?array
    {
        $row = null;

        $baseQuery = DB::table('bookings as b')
            ->leftJoin('services as s', 's.id', '=', 'b.service_id')
            ->leftJoin('users as tm', 'tm.id', '=', 'b.assigned_tech_id')
            ->select(
                'b.*',
                's.title as service_name',
                'tm.name as tech_name',
                'tm.role as tech_role',
                'tm.avatar_url as tech_image_url' // Assumed `avatar_url` from typical user tables, might need adjustment if it was specifically `image_url` on team_members
            )
            ->where('b.status', 'completed');

        // Note: The legacy query joined against `team_members`.
        // If team_members is a separate table, adjust back to `team_members as tm`.
        // I will use team_members to be safe and match legacy.

        $baseQuery = DB::table('bookings as b')
            ->leftJoin('services as s', 's.id', '=', 'b.service_id')
            ->leftJoin('team_members as tm', 'tm.id', '=', 'b.assigned_tech_id')
            ->select(
                'b.*',
                's.title as service_name',
                'tm.name as tech_name',
                'tm.role as tech_role',
                'tm.image_url as tech_image_url'
            )
            ->where('b.status', 'completed');

        // Check if build_slug matches
        $row = clone $baseQuery
            ->where('b.build_slug', $slug)
            ->orderBy('b.created_at', 'desc')
            ->first();

        if (! $row) {
            // Brute force check by reference number or ID-based slug
            $rows = clone $baseQuery
                ->orderBy('b.created_at', 'desc')
                ->get();

            foreach ($rows as $candidate) {
                $ref = $this->makeSlug((string) ($candidate->reference_number ?? ''));

                if ($ref !== '' && $ref === $slug) {
                    $row = $candidate;
                    break;
                }

                $idSlug = $this->makeSlug((string) ($candidate->id ?? ''));
                if ($idSlug !== '' && ('booking-'.$idSlug) === $slug) {
                    $row = $candidate;
                    break;
                }
            }
        }

        if (! $row) {
            return null;
        }

        // Fetch build-progress updates for this booking
        $buildUpdates = [];
        try {
            $bookingId = (string) ($row->id ?? '');
            if ($bookingId !== '') {
                $buildUpdates = DB::table('build_updates')
                    ->where('booking_id', $bookingId)
                    ->orderBy('created_at', 'asc')
                    ->get(['note', 'photo_urls', 'created_at'])
                    ->map(fn ($item) => (array) $item)
                    ->toArray();
            }
        } catch (\Throwable) {
            // build_updates table may not exist yet
        }

        return $this->mapBookingBuildRow((array) $row, $slug, $buildUpdates);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, mixed>>  $buildUpdates
     * @return array<string, mixed>
     */
    private function mapBookingBuildRow(array $row, string $slug, array $buildUpdates = []): array
    {
        $after = $this->decodeJsonUrls($row['after_media_urls'] ?? null);
        $before = $this->decodeJsonUrls($row['before_media_urls'] ?? null);
        $media = $this->decodeJsonUrls($row['media_urls'] ?? null);

        // Lead images: prefer after first, then before, then general media
        $images = array_values(array_unique(array_merge($after, $before, $media)));

        $serviceName = trim((string) ($row['service_name'] ?? ''));
        $title = $serviceName !== '' ? $serviceName : 'Completed Build';

        $reference = trim((string) ($row['reference_number'] ?? ''));

        // Build a rich description using vehicle + service info
        $vehicleMake = trim((string) ($row['vehicle_make'] ?? ''));
        $vehicleModel = trim((string) ($row['vehicle_model'] ?? ''));
        $vehicleYear = trim((string) ($row['vehicle_year'] ?? ''));
        $vehicleInfo = trim((string) ($row['vehicle_info'] ?? ''));

        $vehicleLine = implode(' ', array_filter([$vehicleYear, $vehicleMake, $vehicleModel]));
        if ($vehicleLine === '') {
            $vehicleLine = $vehicleInfo;
        }

        $description = $serviceName !== '' && $vehicleLine !== ''
            ? "{$serviceName} build on a {$vehicleLine}."
            : ($reference !== '' ? "Build showcase for booking {$reference}." : 'Build showcase from a completed booking.');

        // Parse parts notes into an array of items
        $partsNotes = trim((string) ($row['parts_notes'] ?? ''));
        $partsArray = [];
        if ($partsNotes !== '') {
            $lines = preg_split('/\r?\n|,/', $partsNotes) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $partsArray[] = $line;
                }
            }
        }

        // Map build-progress updates
        $updates = [];
        foreach ($buildUpdates as $u) {
            $note = trim((string) ($u['note'] ?? ''));
            $photoUrls = $this->decodeJsonUrls($u['photo_urls'] ?? null);
            if ($note !== '' || count($photoUrls) > 0) {
                $updates[] = [
                    'note' => $note,
                    'photoUrls' => $photoUrls,
                    'createdAt' => (string) ($u['created_at'] ?? ''),
                ];
            }
        }

        // Technician info
        $techName = trim((string) ($row['tech_name'] ?? ''));
        $techRole = trim((string) ($row['tech_role'] ?? ''));
        $techImageUrl = trim((string) ($row['tech_image_url'] ?? ''));

        $appointmentDate = trim((string) ($row['appointment_date'] ?? ''));
        $customerNotes = trim((string) ($row['notes'] ?? ''));

        return [
            'id' => 0,
            'title' => $title,
            'slug' => $slug,
            'category' => 'Booking',
            'description' => $description,
            'imageUrl' => $images[0] ?? '',
            'images' => $images,
            'sortOrder' => 0,
            'isActive' => true,
            'createdAt' => (string) ($row['created_at'] ?? date('c')),
            'updatedAt' => (string) ($row['updated_at'] ?? ($row['created_at'] ?? date('c'))),
            // Enriched build data
            'serviceName' => $serviceName,
            'referenceNumber' => $reference,
            'vehicle' => [
                'make' => $vehicleMake,
                'model' => $vehicleModel,
                'year' => $vehicleYear,
                'info' => $vehicleInfo,
                'label' => $vehicleLine,
            ],
            'technician' => $techName,
            'technicianRole' => $techRole,
            'technicianImage' => $techImageUrl,
            'appointmentDate' => $appointmentDate,
            'notes' => $customerNotes,
            'parts' => $partsArray,
            'buildUpdates' => $updates,
            'beforeImages' => $before,
            'afterImages' => $after,
        ];
    }

    /** @return string[] */
    private function decodeJsonUrls(mixed $raw): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $urls = [];
        foreach ($decoded as $value) {
            if (! is_string($value)) {
                continue;
            }
            $trimmed = trim($value);
            if ($trimmed !== '') {
                $urls[] = $trimmed;
            }
        }

        return array_values(array_unique($urls));
    }
}
