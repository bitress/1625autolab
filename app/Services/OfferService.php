<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * OfferService
 *
 * Full CRUD for the offers table.
 *
 * Public-facing endpoints return only active offers (is_active = 1).
 * Admin endpoints return all offers.
 */
class OfferService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * All offers ordered by sort_order, id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(bool $includeInactive = false): array
    {
        $query = DB::table('offers')->orderBy('sort_order', 'asc')->orderBy('id', 'asc');

        if (! $includeInactive) {
            $query->where('is_active', 1);
        }

        return $query->get()->map(fn ($row) => $this->mapRow((array) $row))->toArray();
    }

    /**
     * Single offer by ID.
     *
     * @return array<string, mixed>
     */
    public function getById(int $id, bool $requireActive = true): array
    {
        $query = DB::table('offers')->where('id', $id);

        if ($requireActive) {
            $query->where('is_active', 1);
        }

        $row = $query->first();
        if (! $row) {
            throw new RuntimeException('Offer not found.', 404);
        }

        return $this->mapRow((array) $row);
    }

    /**
     * Create a new offer. Returns the created record.
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

        $id = DB::table('offers')->insertGetId($params);

        $created = $this->getById($id, false);
        $this->logOfferCreated($created);

        return $created;
    }

    /**
     * Update an existing offer. Returns the updated record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        $current = $this->getById($id, false);

        $merged = array_merge([
            'title' => $current['title'],
            'subtitle' => $current['subtitle'],
            'description' => $current['description'],
            'badgeText' => $current['badgeText'],
            'ctaText' => $current['ctaText'],
            'ctaUrl' => $current['ctaUrl'],
            'linkedServiceId' => $current['linkedServiceId'],
            'linkedProductId' => $current['linkedProductId'],
            'sortOrder' => $current['sortOrder'],
            'isActive' => $current['isActive'],
        ], $data);

        $params = $this->bindParams($merged);
        $params['updated_at'] = Carbon::now();

        DB::table('offers')->where('id', $id)->update($params);

        $updated = $this->getById($id, false);
        $this->logOfferUpdated($current, $updated);

        return $updated;
    }

    /**
     * Hard-delete an offer.
     */
    public function delete(int $id): void
    {
        $current = $this->getById($id, false);

        $affected = DB::table('offers')->where('id', $id)->delete();
        if ($affected === 0) {
            throw new RuntimeException('Offer not found.', 404);
        }

        $this->logOfferDeleted($current);
    }

    // -------------------------------------------------------------------------
    // Activity Logging
    // -------------------------------------------------------------------------

    private function logOfferCreated(array $created): void
    {
        $this->logOfferActivity(static function ($logger) use ($created): void {
            $logger->logCreated(['after' => $created], 'offers');
        }, $created);
    }

    private function logOfferUpdated(array $before, array $after): void
    {
        $this->logOfferActivity(static function ($logger) use ($before, $after): void {
            $logger->logUpdated($after, $before, [], 'offers');
        }, $after);
    }

    private function logOfferDeleted(array $before): void
    {
        $this->logOfferActivity(static function ($logger) use ($before): void {
            $logger->logDeleted(['before' => $before], 'offers');
        }, $before);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function logOfferActivity(callable $writer, array $entity): void
    {
        $subjectId = isset($entity['id']) ? (int) $entity['id'] : 0;
        if ($subjectId <= 0) {
            return;
        }

        try {
            if (function_exists('activity')) {
                $logger = activity()->forSubject('offers', $subjectId);
                $actorUserId = $this->resolveActorUserId();
                if ($actorUserId !== null) {
                    $logger->byUser($actorUserId);
                }
                $writer($logger);
            }
        } catch (Throwable $e) {
            error_log('[OfferService] Activity logging failed: '.$e->getMessage());
        }
    }

    private function resolveActorUserId(): ?int
    {
        try {
            $user = Auth::user();

            return $user ? (int) $user->id : null;
        } catch (Throwable) {
            return null;
        }
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
        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => ($row['title'] ?? ''),
            'subtitle' => ($row['subtitle'] ?? ''),
            'description' => ($row['description'] ?? ''),
            'badgeText' => ($row['badge_text'] ?? ''),
            'ctaText' => ($row['cta_text'] ?? ''),
            'ctaUrl' => ($row['cta_url'] ?? ''),
            'linkedServiceId' => isset($row['linked_service_id']) && $row['linked_service_id'] !== null
                                    ? (int) $row['linked_service_id'] : null,
            'linkedProductId' => isset($row['linked_product_id']) && $row['linked_product_id'] !== null
                                    ? (int) $row['linked_product_id'] : null,
            'isActive' => (bool) ($row['is_active'] ?? false),
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'createdAt' => ($row['created_at'] ?? ''),
            'updatedAt' => ($row['updated_at'] ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private function bindParams(array $data): array
    {
        $linkedServiceId = $data['linkedServiceId'] ?? ($data['linked_service_id'] ?? null);
        $linkedProductId = $data['linkedProductId'] ?? ($data['linked_product_id'] ?? null);

        return [
            'title' => $data['title'] ?? '',
            'subtitle' => $data['subtitle'] ?? '',
            'description' => $data['description'] ?? '',
            'badge_text' => $data['badgeText'] ?? ($data['badge_text'] ?? 'Limited Time Offer'),
            'cta_text' => $data['ctaText'] ?? ($data['cta_text'] ?? 'Claim Your Offer'),
            'cta_url' => $data['ctaUrl'] ?? ($data['cta_url'] ?? '#contact'),
            'linked_service_id' => $linkedServiceId !== null && $linkedServiceId !== '' ? (int) $linkedServiceId : null,
            'linked_product_id' => $linkedProductId !== null && $linkedProductId !== '' ? (int) $linkedProductId : null,
            'sort_order' => (int) ($data['sortOrder'] ?? ($data['sort_order'] ?? 0)),
            'is_active' => (int) ($data['isActive'] ?? ($data['is_active'] ?? 1)),
        ];
    }

    /** @param array<string, mixed> $data */
    private function validatePayload(array $data): void
    {
        if (empty(trim($data['title'] ?? ''))) {
            throw new RuntimeException('Offer title is required.', 422);
        }
    }
}
