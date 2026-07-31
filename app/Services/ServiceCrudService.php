<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * ServiceCrudService
 *
 * Full CRUD for the services table.
 *
 * Public-facing endpoints return only active services (is_active = 1).
 * Admin endpoints return all services.
 */
class ServiceCrudService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * All active services ordered by sort_order, id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(bool $includeInactive = false): array
    {
        $query = DB::table('services')->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
        if (! $includeInactive) {
            $query->where('is_active', 1);
        }

        $rows = $query->get();

        $features = $this->dbFetchAllFeatures();
        $variations = $this->dbFetchAllVariations();

        return $rows->map(fn ($row) => $this->mapRow(
            (array) $row,
            $features[(int) $row->id] ?? [],
            $variations[(int) $row->id] ?? []
        ))->toArray();
    }

    /**
     * Single service by ID (any active state for admin, active-only for public).
     *
     * @return array<string, mixed>
     */
    public function getById(int $id, bool $requireActive = true): array
    {
        $query = DB::table('services')->where('id', $id);
        if ($requireActive) {
            $query->where('is_active', 1);
        }

        $row = $query->first();
        if (! $row) {
            throw new RuntimeException('Service not found.', 404);
        }

        return $this->mapRow(
            (array) $row,
            $this->dbFetchFeatures($id),
            $this->dbFetchVariations($id)
        );
    }

    /**
     * Single service by slug (any active state for admin, active-only for public).
     *
     * @return array<string, mixed>
     */
    public function getBySlug(string $slug, bool $requireActive = true): array
    {
        $query = DB::table('services')->where('slug', $slug);
        if ($requireActive) {
            $query->where('is_active', 1);
        }

        $row = $query->first();
        if (! $row) {
            throw new RuntimeException('Service not found.', 404);
        }

        $id = (int) $row->id;

        return $this->mapRow(
            (array) $row,
            $this->dbFetchFeatures($id),
            $this->dbFetchVariations($id)
        );
    }

    /**
     * Create a new service. Returns the created record.
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

        $id = DB::table('services')->insertGetId($params);

        $this->dbReplaceFeatures($id, $data['features'] ?? []);

        $created = $this->getById($id, false);
        $this->logServiceCreated($created);

        return $created;
    }

    /**
     * Update an existing service. Returns the updated record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        // Fetch current so we can merge partial updates
        $current = $this->getById($id, false);

        $merged = array_merge([
            'title' => $current['title'],
            'slug' => $current['slug'],
            'description' => $current['description'],
            'fullDescription' => $current['fullDescription'],
            'icon' => $current['icon'],
            'imageUrl' => $current['imageUrl'],
            'duration' => $current['duration'],
            'startingPrice' => $current['startingPrice'],
            'features' => $current['features'],
            'sortOrder' => $current['sortOrder'],
            'isActive' => $current['isActive'],
        ], $data);

        $params = $this->bindParams($merged);
        $params['updated_at'] = Carbon::now();

        DB::table('services')->where('id', $id)->update($params);

        $this->dbReplaceFeatures($id, $merged['features'] ?? []);

        $oldImage = trim((string) ($current['imageUrl'] ?? ''));
        $newImage = trim((string) ($merged['imageUrl'] ?? ($merged['image_url'] ?? '')));
        if ($oldImage !== '' && $oldImage !== $newImage) {
            $this->deleteManagedImageUrl($oldImage);
        }

        $updated = $this->getById($id, false);
        $this->logServiceUpdated($current, $updated);

        return $updated;
    }

    /**
     * Hard-delete a service.
     */
    public function delete(int $id): void
    {
        $current = $this->getById($id, false);
        $variations = $this->dbFetchVariations($id);

        $affected = DB::table('services')->where('id', $id)->delete();
        if ($affected === 0) {
            throw new RuntimeException('Service not found.', 404);
        }

        $urls = $this->collectServiceImageUrls($current);
        foreach ($variations as $variation) {
            $urls = array_merge($urls, $this->collectVariationImageUrls($variation));
        }
        $this->deleteManagedImageUrls($urls);

        $this->logServiceDeleted($current);
    }

    // -------------------------------------------------------------------------
    // DB – service_features helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch features for a single service.
     *
     * @return string[]
     */
    private function dbFetchFeatures(int $serviceId): array
    {
        return DB::table('service_features')
            ->where('service_id', $serviceId)
            ->orderBy('sort_order', 'asc')
            ->pluck('feature')
            ->toArray();
    }

    /**
     * Fetch features for all services in a single query.
     *
     * @return array<int, string[]> service_id => feature[]
     */
    private function dbFetchAllFeatures(): array
    {
        $rows = DB::table('service_features')
            ->orderBy('service_id', 'asc')
            ->orderBy('sort_order', 'asc')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->service_id][] = $row->feature;
        }

        return $map;
    }

    /**
     * Replace all features for a service (delete then re-insert).
     *
     * @param  string[]  $features
     */
    private function dbReplaceFeatures(int $serviceId, array $features): void
    {
        DB::table('service_features')->where('service_id', $serviceId)->delete();

        if (empty($features)) {
            return;
        }

        $inserts = [];
        foreach (array_values($features) as $i => $feature) {
            $inserts[] = [
                'service_id' => $serviceId,
                'feature' => $feature,
                'sort_order' => $i + 1,
            ];
        }

        DB::table('service_features')->insert($inserts);
    }

    // -------------------------------------------------------------------------
    // DB – service_variations helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch all variations for a single service.
     *
     * @return array<int, array<string, mixed>>
     */
    private function dbFetchVariations(int $serviceId): array
    {
        $rows = DB::table('service_variations')
            ->where('service_id', $serviceId)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return $rows->map(fn ($row) => $this->mapVariationRow((array) $row))->toArray();
    }

    /**
     * Fetch all variations for all services in one query.
     *
     * @return array<int, array<int, array<string, mixed>>> service_id => variation[]
     */
    private function dbFetchAllVariations(): array
    {
        $rows = DB::table('service_variations')
            ->orderBy('service_id', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->service_id][] = $this->mapVariationRow((array) $row);
        }

        return $map;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function mapVariationRow(array $row): array
    {
        $images = json_decode($row['images'] ?? '[]', true);
        if (! is_array($images)) {
            $images = [];
        }
        $specs = json_decode($row['specs'] ?? '[]', true);
        if (! is_array($specs)) {
            $specs = [];
        }
        $colors = json_decode($row['colors'] ?? '[]', true);
        if (! is_array($colors)) {
            $colors = [];
        }
        $colorImages = json_decode($row['color_images'] ?? '{}', true);
        if (! is_array($colorImages)) {
            $colorImages = [];
        }

        return [
            'id' => (int) $row['id'],
            'serviceId' => (int) $row['service_id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => $row['price'],
            'images' => $images,
            'specs' => $specs,
            'colors' => $colors,
            'colorImages' => $colorImages,
            'sortOrder' => (int) $row['sort_order'],
        ];
    }

    /**
     * Public: create a variation for a service.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createVariation(int $serviceId, array $data): array
    {
        $this->getById($serviceId, false); // throws 404 if service missing

        $params = $this->bindVariationParams($serviceId, $data);
        $varId = DB::table('service_variations')->insertGetId($params);

        $created = $this->dbFetchVariationById($varId);
        $this->logServiceVariationCreated($serviceId, $created);

        return $created;
    }

    /**
     * Public: update a variation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateVariation(int $serviceId, int $varId, array $data): array
    {
        $this->getById($serviceId, false); // throws 404 if service missing
        $current = $this->dbFetchVariationById($varId);

        $merged = array_merge([
            'name' => $current['name'],
            'description' => $current['description'],
            'price' => $current['price'],
            'images' => $current['images'],
            'specs' => $current['specs'],
            'colors' => $current['colors'] ?? [],
            'colorImages' => $current['colorImages'] ?? [],
            'sortOrder' => $current['sortOrder'],
        ], $data);

        $params = $this->bindVariationParams($serviceId, $merged);

        DB::table('service_variations')->where('id', $varId)->where('service_id', $serviceId)->update($params);

        $newVariation = [
            'images' => json_decode((string) ($params['images'] ?? '[]'), true) ?: [],
            'colorImages' => json_decode((string) ($params['color_images'] ?? '{}'), true) ?: [],
        ];

        $this->deleteRemovedManagedUrls(
            $this->collectVariationImageUrls($current),
            $this->collectVariationImageUrls($newVariation)
        );

        $updated = $this->dbFetchVariationById($varId);
        $this->logServiceVariationUpdated($serviceId, $current, $updated);

        return $updated;
    }

    /**
     * Public: delete a variation.
     */
    public function deleteVariation(int $serviceId, int $varId): void
    {
        $current = $this->dbFetchVariationById($varId);

        $affected = DB::table('service_variations')->where('id', $varId)->where('service_id', $serviceId)->delete();
        if ($affected === 0) {
            throw new RuntimeException('Variation not found.', 404);
        }

        $this->deleteManagedImageUrls($this->collectVariationImageUrls($current));
        $this->logServiceVariationDeleted($serviceId, $current);
    }

    /** @return array<string, mixed> */
    private function dbFetchVariationById(int $varId): array
    {
        $row = DB::table('service_variations')->where('id', $varId)->first();
        if (! $row) {
            throw new RuntimeException('Variation not found.', 404);
        }

        return $this->mapVariationRow((array) $row);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function bindVariationParams(int $serviceId, array $data): array
    {
        $images = $data['images'] ?? [];
        if (! is_array($images)) {
            $images = [];
        }
        $specs = $data['specs'] ?? [];
        if (! is_array($specs)) {
            $specs = [];
        }
        $colors = $data['colors'] ?? [];
        if (! is_array($colors)) {
            $colors = [];
        }
        $colors = array_values(array_filter(array_map(
            fn ($color) => is_string($color) ? trim($color) : '',
            $colors
        ), fn ($color) => $color !== ''));
        $colorImages = $data['colorImages'] ?? ($data['color_images'] ?? []);
        if (! is_array($colorImages)) {
            $colorImages = [];
        }

        $normalizedColorImages = [];
        foreach ($colorImages as $color => $urls) {
            if (! is_string($color)) {
                continue;
            }
            $normalizedColor = trim($color);
            if ($normalizedColor === '') {
                continue;
            }
            if (! is_array($urls)) {
                continue;
            }
            $normalizedUrls = array_values(array_filter(array_map(
                fn ($url) => is_string($url) ? trim($url) : '',
                $urls
            ), fn ($url) => $url !== ''));
            if (! empty($normalizedUrls)) {
                $normalizedColorImages[$normalizedColor] = $normalizedUrls;
            }
        }

        // Keep color-image mappings aligned to declared colors only.
        if (! empty($colors)) {
            $normalizedColorImages = array_intersect_key(
                $normalizedColorImages,
                array_flip($colors)
            );
        } else {
            $normalizedColorImages = [];
        }

        return [
            'service_id' => $serviceId,
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'price' => $data['price'] ?? '',
            'images' => json_encode($images),
            'specs' => json_encode($specs),
            'colors' => json_encode($colors),
            'color_images' => json_encode($normalizedColorImages),
            'sort_order' => (int) ($data['sortOrder'] ?? ($data['sort_order'] ?? 0)),
        ];
    }

    // -------------------------------------------------------------------------
    // Activity Logging
    // -------------------------------------------------------------------------

    private function logServiceCreated(array $created): void
    {
        $this->logServiceActivity(static function ($logger) use ($created): void {
            $logger->logCreated(['after' => $created], 'services');
        }, $created);
    }

    private function logServiceUpdated(array $before, array $after): void
    {
        $this->logServiceActivity(static function ($logger) use ($before, $after): void {
            $logger->logUpdated($after, $before, [], 'services');
        }, $after);
    }

    private function logServiceDeleted(array $before): void
    {
        $this->logServiceActivity(static function ($logger) use ($before): void {
            $logger->logDeleted(['before' => $before], 'services');
        }, $before);
    }

    private function logServiceVariationCreated(int $serviceId, array $variation): void
    {
        $this->logServiceActivity(static function ($logger) use ($variation): void {
            $logger
                ->withProperties([
                    'entity' => 'variation',
                    'after' => $variation,
                ])
                ->log('PRODUCT_VARIATION_CREATED', 'services'); // Used PRODUCT_VARIATION_CREATED in original
        }, ['id' => $serviceId]);
    }

    private function logServiceVariationUpdated(int $serviceId, array $before, array $after): void
    {
        $this->logServiceActivity(static function ($logger) use ($before, $after): void {
            $logger
                ->withProperty('entity', 'variation')
                ->logUpdated($after, $before, [], 'services');
        }, ['id' => $serviceId]);
    }

    private function logServiceVariationDeleted(int $serviceId, array $before): void
    {
        $this->logServiceActivity(static function ($logger) use ($before): void {
            $logger
                ->withProperties([
                    'entity' => 'variation',
                    'before' => $before,
                ])
                ->log('PRODUCT_VARIATION_DELETED', 'services');
        }, ['id' => $serviceId]);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function logServiceActivity(callable $writer, array $entity): void
    {
        $subjectId = isset($entity['id']) ? (int) $entity['id'] : 0;
        if ($subjectId <= 0) {
            return;
        }

        try {
            if (function_exists('activity')) {
                $logger = activity()->forSubject('services', $subjectId);
                $actorUserId = $this->resolveActorUserId();
                if ($actorUserId !== null) {
                    $logger->byUser($actorUserId);
                }
                $writer($logger);
            }
        } catch (Throwable $e) {
            error_log('[ServiceCrudService] Activity logging failed: '.$e->getMessage());
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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Map DB row to camelCase API shape.
     * @param  array<string, mixed>  $row  Raw fetch row from the services table.
     * @param  string[]  $features  Ordered feature strings from service_features.
     * @param  array<int, array<string, mixed>>  $variations  Variations array for the service.
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $features = [], array $variations = []): array
    {
        return [
            'id' => (int) $row['id'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'description' => $row['description'],
            'fullDescription' => $row['full_description'],
            'icon' => $row['icon'],
            'imageUrl' => $row['image_url'],
            'duration' => $row['duration'],
            'startingPrice' => $row['starting_price'],
            'features' => $features,
            'variations' => $variations,
            'sortOrder' => (int) $row['sort_order'],
            'isActive' => (bool) $row['is_active'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    /** Build PDO bind params from camelCase $data. @return array<string, mixed> */
    private function bindParams(array $data): array
    {
        $title = $data['title'] ?? '';
        $slug = trim($data['slug'] ?? '');
        if ($slug === '') {
            $slug = $this->makeSlug($title);
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'description' => $data['description'] ?? '',
            'full_description' => $data['fullDescription'] ?? ($data['full_description'] ?? ''),
            'icon' => $data['icon'] ?? 'Wrench',
            'image_url' => $data['imageUrl'] ?? ($data['image_url'] ?? ''),
            'duration' => $data['duration'] ?? '',
            'starting_price' => $data['startingPrice'] ?? ($data['starting_price'] ?? ''),
            'sort_order' => (int) ($data['sortOrder'] ?? ($data['sort_order'] ?? 0)),
            'is_active' => (int) ($data['isActive'] ?? ($data['is_active'] ?? 1)),
        ];
    }

    /** Convert a title to a URL-safe slug. */
    private function makeSlug(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;

        return trim($slug, '-');
    }

    /** @param array<string, mixed> $data */
    private function validatePayload(array $data): void
    {
        if (empty(trim($data['title'] ?? ''))) {
            throw new RuntimeException('Service title is required.', 422);
        }
        if (empty(trim($data['description'] ?? ''))) {
            throw new RuntimeException('Service description is required.', 422);
        }
    }

    /** @param array<string, mixed> $service @return string[] */
    private function collectServiceImageUrls(array $service): array
    {
        $url = trim((string) ($service['imageUrl'] ?? ($service['image_url'] ?? '')));

        return $url !== '' ? [$url] : [];
    }

    /** @param array<string, mixed> $variation @return string[] */
    private function collectVariationImageUrls(array $variation): array
    {
        $urls = [];

        $images = $variation['images'] ?? [];
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

        $colorImages = $variation['colorImages'] ?? ($variation['color_images'] ?? []);
        if (is_array($colorImages)) {
            foreach ($colorImages as $group) {
                if (! is_array($group)) {
                    continue;
                }
                foreach ($group as $url) {
                    if (! is_string($url)) {
                        continue;
                    }
                    $trimmed = trim($url);
                    if ($trimmed !== '') {
                        $urls[] = $trimmed;
                    }
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /** @param string[] $oldUrls @param string[] $newUrls */
    private function deleteRemovedManagedUrls(array $oldUrls, array $newUrls): void
    {
        $this->deleteManagedImageUrls(array_values(array_diff($oldUrls, $newUrls)));
    }

    /** @param string[] $urls */
    private function deleteManagedImageUrls(array $urls): void
    {
        if (empty($urls)) {
            return;
        }

        $storage = new UploadStorage;
        foreach (array_values(array_unique($urls)) as $url) {
            $this->deleteManagedImageUrl((string) $url, $storage);
        }
    }

    private function deleteManagedImageUrl(string $url, ?UploadStorage $storage = null): void
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return;
        }

        try {
            ($storage ?? new UploadStorage)->deleteByUrl($trimmed);
        } catch (Throwable) {
            // Keep CRUD successful even if storage cleanup fails.
        }
    }
}
