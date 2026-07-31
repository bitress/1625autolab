<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * FaqService
 *
 * Full CRUD for the faqs table.
 */
class FaqService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function getAll(bool $activeOnly = false): array
    {
        $query = DB::table('faqs')->orderBy('sort_order', 'asc')->orderBy('id', 'asc');

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->get()->map(fn ($row) => $this->mapRow((array) $row))->toArray();
    }

    /** @return array<string, mixed> */
    public function getById(int $id): array
    {
        $row = DB::table('faqs')->where('id', $id)->first();

        if (! $row) {
            throw new RuntimeException('FAQ not found.', 404);
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

        $id = DB::table('faqs')->insertGetId($params);
        $created = $this->getById($id);

        $this->logFaqActivity('FAQ_CREATED', $created, ['after' => $created]);

        return $created;
    }

    /** @return array<string, mixed> */
    public function update(int $id, array $data): array
    {
        $before = $this->getById($id);

        $merged = array_merge([
            'question' => $before['question'],
            'answer' => $before['answer'],
            'category' => $before['category'],
            'isActive' => $before['isActive'],
            'sortOrder' => $before['sortOrder'],
        ], $data);

        $params = $this->bindParams($merged);
        $params['updated_at'] = Carbon::now();

        DB::table('faqs')->where('id', $id)->update($params);
        $updated = $this->getById($id);

        $this->logFaqActivity('FAQ_UPDATED', $updated, [
            'before' => $before,
            'after' => $updated,
        ]);

        return $updated;
    }

    public function delete(int $id): void
    {
        $before = $this->getById($id);

        $deleted = DB::table('faqs')->where('id', $id)->delete();

        if ($deleted === 0) {
            throw new RuntimeException('FAQ not found.', 404);
        }

        $this->logFaqActivity('FAQ_DELETED', $before, ['before' => $before]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'question' => $row['question'],
            'answer' => $row['answer'],
            'category' => $row['category'] ?? 'General',
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'isActive' => (bool) ($row['is_active'] ?? true),
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    /** @return array<string, mixed> */
    private function bindParams(array $data): array
    {
        return [
            'question' => $data['question'] ?? '',
            'answer' => $data['answer'] ?? '',
            'category' => $data['category'] ?? 'General',
            'sort_order' => (int) ($data['sortOrder'] ?? $data['sort_order'] ?? 0),
            'is_active' => (int) (isset($data['isActive']) ? (bool) $data['isActive'] : true),
        ];
    }

    private function validatePayload(array $data): void
    {
        if (empty(trim($data['question'] ?? ''))) {
            throw new RuntimeException('FAQ question is required.', 422);
        }
        if (empty(trim($data['answer'] ?? ''))) {
            throw new RuntimeException('FAQ answer is required.', 422);
        }
    }

    /** @param array<string, mixed> $entity @param array<string, mixed> $properties */
    private function logFaqActivity(string $event, array $entity, array $properties = []): void
    {
        try {
            if (function_exists('activity')) {
                $subjectId = (string) ((int) ($entity['id'] ?? 0));
                $logger = activity()->forSubject('faqs', $subjectId);

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
            error_log('[FaqService] Activity logging failed: '.$e->getMessage());
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
