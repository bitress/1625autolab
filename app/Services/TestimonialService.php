<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TestimonialService
 *
 * Full CRUD for the testimonials table.
 */
class TestimonialService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function getAll(bool $activeOnly = false): array
    {
        $query = DB::table('testimonials')->orderBy('sort_order', 'asc')->orderBy('id', 'asc');

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->get()->map(fn ($row) => $this->mapRow((array) $row))->toArray();
    }

    /** @return array<string, mixed> */
    public function getById(int $id): array
    {
        $row = DB::table('testimonials')->where('id', $id)->first();

        if (! $row) {
            throw new RuntimeException('Testimonial not found.', 404);
        }

        return $this->mapRow((array) $row);
    }

    /** @return array<string, mixed> */
    public function create(array $data): array
    {
        $this->validatePayload($data);

        $params = $this->bindParams($data);
        $params['created_at'] = Carbon::now();
        $params['updated_at'] = Carbon::now();

        $id = DB::table('testimonials')->insertGetId($params);
        $created = $this->getById($id);

        $this->logTestimonialActivity('TESTIMONIAL_CREATED', $created, ['after' => $created]);

        return $created;
    }

    /** @return array<string, mixed> */
    public function update(int $id, array $data): array
    {
        $before = $this->getById($id);

        $merged = array_merge([
            'name' => $before['name'],
            'role' => $before['role'],
            'content' => $before['content'],
            'rating' => $before['rating'],
            'imageUrl' => $before['imageUrl'],
            'isActive' => $before['isActive'],
            'sortOrder' => $before['sortOrder'],
        ], $data);

        $params = $this->bindParams($merged);
        $params['updated_at'] = Carbon::now();

        DB::table('testimonials')->where('id', $id)->update($params);

        $oldImage = (string) ($before['imageUrl'] ?? '');
        $newImage = (string) ($merged['imageUrl'] ?? ($merged['image_url'] ?? ''));

        if ($oldImage !== '' && $oldImage !== $newImage) {
            $this->deleteManagedImageUrl($oldImage);
        }

        $updated = $this->getById($id);

        $this->logTestimonialActivity('TESTIMONIAL_UPDATED', $updated, [
            'before' => $before,
            'after' => $updated,
        ]);

        return $updated;
    }

    public function delete(int $id): void
    {
        $before = $this->getById($id);

        $deleted = DB::table('testimonials')->where('id', $id)->delete();

        if ($deleted === 0) {
            throw new RuntimeException('Testimonial not found.', 404);
        }

        $oldImage = (string) ($before['imageUrl'] ?? '');
        if ($oldImage !== '') {
            $this->deleteManagedImageUrl($oldImage);
        }

        $this->logTestimonialActivity('TESTIMONIAL_DELETED', $before, ['before' => $before]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'role' => $row['role'] ?? '',
            'content' => $row['content'],
            'rating' => (int) ($row['rating'] ?? 5),
            'imageUrl' => $row['image_url'] ?? null,
            'isActive' => (bool) ($row['is_active'] ?? true),
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    /** @return array<string, mixed> */
    private function bindParams(array $data): array
    {
        return [
            'name' => $data['name'] ?? '',
            'role' => $data['role'] ?? '',
            'content' => $data['content'] ?? '',
            'rating' => (int) ($data['rating'] ?? 5),
            'image_url' => $data['imageUrl'] ?? ($data['image_url'] ?? null),
            'is_active' => (int) (isset($data['isActive']) ? (bool) $data['isActive'] : true),
            'sort_order' => (int) ($data['sortOrder'] ?? $data['sort_order'] ?? 0),
        ];
    }

    private function validatePayload(array $data): void
    {
        if (empty(trim($data['name'] ?? ''))) {
            throw new RuntimeException('Testimonial name is required.', 422);
        }
        if (empty(trim($data['content'] ?? ''))) {
            throw new RuntimeException('Testimonial content is required.', 422);
        }
    }

    private function deleteManagedImageUrl(string $url): void
    {
        if (class_exists(UploadStorage::class)) {
            try {
                app(UploadStorage::class)->deleteByUrl($url);
            } catch (\Throwable) {
                // Keep CRUD successful even if storage cleanup fails.
            }
        }
    }

    /** @param array<string, mixed> $entity @param array<string, mixed> $properties */
    private function logTestimonialActivity(string $event, array $entity, array $properties = []): void
    {
        try {
            if (function_exists('activity')) {
                $subjectId = (string) ((int) ($entity['id'] ?? 0));
                $logger = activity()->forSubject('testimonials', $subjectId);

                $actorUserId = $this->resolveActorUserId();
                if ($actorUserId !== null && $actorUserId > 0) {
                    $logger->byUser($actorUserId);
                }

                if ($properties !== []) {
                    $logger->withProperties($properties);
                }

                $logger->log($event, 'content');
            }
        } catch (\Throwable $e) {
            error_log('[TestimonialService] Activity logging failed: '.$e->getMessage());
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
