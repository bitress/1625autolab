<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * ProductService
 *
 * Full CRUD for the products table.
 *
 * Public-facing endpoints return only active products (is_active = 1).
 * Admin endpoints return all products.
 */
class ProductService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * All products ordered by sort_order, id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(bool $includeInactive = false): array
    {
        $query = DB::table('products')->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
        if (! $includeInactive) {
            $query->where('is_active', 1);
        }

        $rows = $query->get();
        $variations = $this->dbFetchAllVariations();

        return $rows->map(fn ($row) => $this->mapRow((array) $row, $variations[(int) $row->id] ?? []))->toArray();
    }

    /**
     * Single product by ID.
     *
     * @return array<string, mixed>
     */
    public function getById(int $id, bool $requireActive = true): array
    {
        $query = DB::table('products')->where('id', $id);
        if ($requireActive) {
            $query->where('is_active', 1);
        }

        $row = $query->first();
        if (! $row) {
            throw new RuntimeException('Product not found.', 404);
        }

        return $this->mapRow((array) $row, $this->dbFetchVariations($id));
    }

    /**
     * Single product by identifier (UUID preferred, numeric ID fallback).
     *
     * @return array<string, mixed>
     */
    public function getByIdentifier(string $identifier, bool $requireActive = true): array
    {
        $trimmed = trim($identifier);
        if ($trimmed === '') {
            throw new RuntimeException('Product not found.', 404);
        }

        if ($this->isUuid($trimmed)) {
            $query = DB::table('products')->where('uuid', $trimmed);
            if ($requireActive) {
                $query->where('is_active', 1);
            }

            $row = $query->first();
            if (! $row) {
                throw new RuntimeException('Product not found.', 404);
            }

            return $this->mapRow((array) $row, $this->dbFetchVariations((int) $row->id));
        }

        if (ctype_digit($trimmed)) {
            return $this->getById((int) $trimmed, $requireActive);
        }

        throw new RuntimeException('Product not found.', 404);
    }

    /** Resolve product numeric ID from UUID or numeric identifier. */
    public function resolveId(string $identifier): int
    {
        $trimmed = trim($identifier);
        if ($trimmed === '') {
            throw new RuntimeException('Product not found.', 404);
        }

        if (ctype_digit($trimmed)) {
            return (int) $trimmed;
        }

        if ($this->isUuid($trimmed)) {
            $row = DB::table('products')->where('uuid', $trimmed)->first(['id']);
            if (! $row) {
                throw new RuntimeException('Product not found.', 404);
            }

            return (int) $row->id;
        }

        throw new RuntimeException('Product not found.', 404);
    }

    /**
     * Create a new product. Returns the created record.
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

        $id = DB::table('products')->insertGetId($params);

        $created = $this->getById($id, false);
        $this->logProductCreated($created);

        return $created;
    }

    /**
     * Update an existing product. Returns the updated record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        $current = $this->getById($id, false);

        $merged = array_merge([
            'name' => $current['name'],
            'description' => $current['description'],
            'price' => $current['price'],
            'category' => $current['category'],
            'imageUrl' => $current['imageUrl'],
            'features' => $current['features'],
            'sortOrder' => $current['sortOrder'],
            'isActive' => $current['isActive'],
            'trackStock' => $current['trackStock'] ?? true,
            'stockQty' => $current['stockQty'] ?? 0,
        ], $data);

        $params = $this->bindParams($merged);
        // Do not update UUID on an existing record unless intended, but bindParams handles it.
        // We'll strip UUID just in case since it's immutable normally, or just let update handle it.
        unset($params['uuid']);
        $params['updated_at'] = Carbon::now();

        DB::table('products')->where('id', $id)->update($params);

        $oldImage = trim((string) ($current['imageUrl'] ?? ''));
        $newImage = trim((string) ($merged['imageUrl'] ?? ($merged['image_url'] ?? '')));
        if ($oldImage !== '' && $oldImage !== $newImage) {
            $this->deleteManagedImageUrl($oldImage);
        }

        $updated = $this->getById($id, false);
        $this->logProductUpdated($current, $updated);

        return $updated;
    }

    /**
     * Hard-delete a product.
     */
    public function delete(int $id): void
    {
        $current = $this->getById($id, false);
        $variations = $this->dbFetchVariations($id);

        $affected = DB::table('products')->where('id', $id)->delete();
        if ($affected === 0) {
            throw new RuntimeException('Product not found.', 404);
        }

        $urls = $this->collectProductImageUrls($current);
        foreach ($variations as $variation) {
            $urls = array_merge($urls, $this->collectVariationImageUrls($variation));
        }
        $this->deleteManagedImageUrls($urls);

        $this->logProductDeleted($current);
    }

    // -------------------------------------------------------------------------
    // DB – product_variations helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch all variations for a single product.
     *
     * @return array<int, array<string, mixed>>
     */
    private function dbFetchVariations(int $productId): array
    {
        $rows = DB::table('product_variations')
            ->where('product_id', $productId)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return $rows->map(fn ($row) => $this->mapVariationRow((array) $row))->toArray();
    }

    /**
     * Fetch all variations for all products in one query.
     *
     * @return array<int, array<int, array<string, mixed>>> product_id => variation[]
     */
    private function dbFetchAllVariations(): array
    {
        $rows = DB::table('product_variations')
            ->orderBy('product_id', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->product_id][] = $this->mapVariationRow((array) $row);
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
            'productId' => (int) $row['product_id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => $row['price'],
            'images' => $images,
            'specs' => $specs,
            'colors' => $colors,
            'colorImages' => $colorImages,
            'sortOrder' => (int) $row['sort_order'],
            'trackStock' => isset($row['track_stock']) ? ((int) $row['track_stock'] === 1) : true,
            'stockQty' => isset($row['stock_qty']) ? (int) $row['stock_qty'] : 0,
        ];
    }

    /**
     * Public: create a variation for a product.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createVariation(int $productId, array $data): array
    {
        $this->getById($productId, false); // throws 404 if product missing
        $params = $this->bindVariationParams($productId, $data);

        $varId = DB::table('product_variations')->insertGetId($params);

        $created = $this->dbFetchVariationById($varId);
        $this->logProductVariationCreated($productId, $created);

        return $created;
    }

    /**
     * Public: update a variation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateVariation(int $productId, int $varId, array $data): array
    {
        $this->getById($productId, false); // throws 404 if product missing
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
            'trackStock' => $current['trackStock'] ?? true,
            'stockQty' => $current['stockQty'] ?? 0,
        ], $data);

        $params = $this->bindVariationParams($productId, $merged);

        DB::table('product_variations')->where('id', $varId)->where('product_id', $productId)->update($params);

        $newVariation = [
            'images' => json_decode((string) ($params['images'] ?? '[]'), true) ?: [],
            'colorImages' => json_decode((string) ($params['color_images'] ?? '{}'), true) ?: [],
        ];

        $this->deleteRemovedManagedUrls(
            $this->collectVariationImageUrls($current),
            $this->collectVariationImageUrls($newVariation)
        );

        $updated = $this->dbFetchVariationById($varId);
        $this->logProductVariationUpdated($productId, $current, $updated);

        return $updated;
    }

    /**
     * Public: delete a variation.
     */
    public function deleteVariation(int $productId, int $varId): void
    {
        $current = $this->dbFetchVariationById($varId);

        $affected = DB::table('product_variations')->where('id', $varId)->where('product_id', $productId)->delete();
        if ($affected === 0) {
            throw new RuntimeException('Variation not found.', 404);
        }

        $this->deleteManagedImageUrls($this->collectVariationImageUrls($current));
        $this->logProductVariationDeleted($productId, $current);
    }

    /** @return array<string, mixed> */
    private function dbFetchVariationById(int $varId): array
    {
        $row = DB::table('product_variations')->where('id', $varId)->first();
        if (! $row) {
            throw new RuntimeException('Variation not found.', 404);
        }

        return $this->mapVariationRow((array) $row);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Map a DB row to the camelCase API shape.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, mixed>>  $variations  Variations array for the product.
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $variations = []): array
    {
        $features = $row['features'] ?? '[]';
        if (is_string($features)) {
            $features = json_decode($features, true) ?? [];
        }

        return [
            'id' => (int) $row['id'],
            'uuid' => trim((string) ($row['uuid'] ?? '')) !== ''
                ? (string) $row['uuid']
                : (string) $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => (float) $row['price'],
            'category' => $row['category'],
            'imageUrl' => $row['image_url'],
            'features' => $features,
            'variations' => $variations,
            'sortOrder' => (int) $row['sort_order'],
            'isActive' => (bool) $row['is_active'],
            'trackStock' => isset($row['track_stock']) ? ((int) $row['track_stock'] === 1) : true,
            'stockQty' => isset($row['stock_qty']) ? (int) $row['stock_qty'] : 0,
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    /** @return array<string, mixed> */
    private function bindParams(array $data): array
    {
        $features = $data['features'] ?? [];
        if (is_string($features)) {
            $features = json_decode($features, true) ?? [];
        }

        return [
            'uuid' => $data['uuid'] ?? $this->uuid(),
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'price' => (float) ($data['price'] ?? 0),
            'category' => $data['category'] ?? '',
            'image_url' => $data['imageUrl'] ?? ($data['image_url'] ?? ''),
            'features' => json_encode(array_values($features)),
            'sort_order' => (int) ($data['sortOrder'] ?? ($data['sort_order'] ?? 0)),
            'is_active' => (int) ($data['isActive'] ?? ($data['is_active'] ?? 1)),
            'track_stock' => (int) ($data['trackStock'] ?? ($data['track_stock'] ?? 1)),
            'stock_qty' => max(0, (int) ($data['stockQty'] ?? ($data['stock_qty'] ?? 0))),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function bindVariationParams(int $productId, array $data): array
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

        if (! empty($colors)) {
            $normalizedColorImages = array_intersect_key(
                $normalizedColorImages,
                array_flip($colors)
            );
        } else {
            $normalizedColorImages = [];
        }

        return [
            'product_id' => $productId,
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'price' => $data['price'] ?? '',
            'images' => json_encode($images),
            'specs' => json_encode($specs),
            'colors' => json_encode($colors),
            'color_images' => json_encode($normalizedColorImages),
            'sort_order' => (int) ($data['sortOrder'] ?? ($data['sort_order'] ?? 0)),
            'track_stock' => (int) ($data['trackStock'] ?? ($data['track_stock'] ?? 1)),
            'stock_qty' => max(0, (int) ($data['stockQty'] ?? ($data['stock_qty'] ?? 0))),
        ];
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        );
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF)
        );
    }

    /** @param array<string, mixed> $data */
    private function validatePayload(array $data): void
    {
        if (empty(trim($data['name'] ?? ''))) {
            throw new RuntimeException('Product name is required.', 422);
        }
        if (! isset($data['price']) || ! is_numeric($data['price']) || (float) $data['price'] < 0) {
            throw new RuntimeException('A valid product price is required.', 422);
        }
        if (isset($data['stockQty']) && (int) $data['stockQty'] < 0) {
            throw new RuntimeException('Stock quantity cannot be negative.', 422);
        }
    }

    /** @param array<string, mixed> $product @return string[] */
    private function collectProductImageUrls(array $product): array
    {
        $url = trim((string) ($product['imageUrl'] ?? ($product['image_url'] ?? '')));

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

    // -------------------------------------------------------------------------
    // Activity Logging
    // -------------------------------------------------------------------------

    private function logProductCreated(array $created): void
    {
        $this->logProductActivity(static function ($logger) use ($created): void {
            $logger->logCreated(['after' => $created], 'products');
        }, $created);
    }

    private function logProductUpdated(array $before, array $after): void
    {
        $this->logProductActivity(static function ($logger) use ($before, $after): void {
            $logger->logUpdated($after, $before, [], 'products');
        }, $after);
    }

    private function logProductDeleted(array $before): void
    {
        $this->logProductActivity(static function ($logger) use ($before): void {
            $logger->logDeleted(['before' => $before], 'products');
        }, $before);
    }

    private function logProductVariationCreated(int $productId, array $variation): void
    {
        $this->logProductActivity(static function ($logger) use ($variation): void {
            $logger
                ->withProperties([
                    'entity' => 'variation',
                    'after' => $variation,
                ])
                ->log('PRODUCT_VARIATION_CREATED', 'products');
        }, ['id' => $productId]);
    }

    private function logProductVariationUpdated(int $productId, array $before, array $after): void
    {
        $this->logProductActivity(static function ($logger) use ($before, $after): void {
            $logger
                ->withProperty('entity', 'variation')
                ->logUpdated($after, $before, [], 'products');
        }, ['id' => $productId]);
    }

    private function logProductVariationDeleted(int $productId, array $before): void
    {
        $this->logProductActivity(static function ($logger) use ($before): void {
            $logger
                ->withProperties([
                    'entity' => 'variation',
                    'before' => $before,
                ])
                ->log('PRODUCT_VARIATION_DELETED', 'products');
        }, ['id' => $productId]);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function logProductActivity(callable $writer, array $entity): void
    {
        $subjectId = isset($entity['id']) ? (int) $entity['id'] : 0;
        if ($subjectId <= 0) {
            return;
        }

        try {
            if (function_exists('activity')) {
                $logger = activity()->forSubject('products', $subjectId);
                $actorUserId = $this->resolveActorUserId();
                if ($actorUserId !== null) {
                    $logger->byUser($actorUserId);
                }
                $writer($logger);
            }
        } catch (Throwable $e) {
            error_log('[ProductService] Activity logging failed: '.$e->getMessage());
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
