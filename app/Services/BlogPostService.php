<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BlogPostService
{
    public function getAll(bool $publishedOnly = false): array
    {
        $query = DB::table('blog_posts');

        if ($publishedOnly) {
            $query->where('status', 'Published');
        }

        $rows = $query->orderBy('created_at', 'desc')->get();

        return array_map([$this, 'mapRow'], $rows->toArray());
    }

    public function getById(int $id, bool $publishedOnly = false): array
    {
        $query = DB::table('blog_posts')->where('id', $id);

        if ($publishedOnly) {
            $query->where('status', 'Published');
        }

        $row = $query->first();

        if (! $row) {
            throw new RuntimeException('Blog post not found.', 404);
        }

        return $this->mapRow((array) $row);
    }

    public function create(array $data): array
    {
        $this->validatePayload($data);

        $id = DB::table('blog_posts')->insertGetId([
            'title' => trim((string) ($data['title'] ?? '')),
            'content' => trim((string) ($data['content'] ?? '')),
            'status' => trim((string) ($data['status'] ?? 'Draft')),
            'cover_image' => $data['coverImage'] ?? ($data['cover_image'] ?? null),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $created = $this->getById($id, false);

        $this->logBlogActivity('BLOG_POST_CREATED', $created, ['after' => $created]);

        return $created;
    }

    public function update(int $id, array $data): array
    {
        $before = $this->getById($id, false);

        $updateData = [];

        if (array_key_exists('title', $data)) {
            $updateData['title'] = trim((string) $data['title']);
        }
        if (array_key_exists('content', $data)) {
            $updateData['content'] = trim((string) $data['content']);
        }
        if (array_key_exists('status', $data)) {
            $updateData['status'] = trim((string) $data['status']);
        }
        if (array_key_exists('coverImage', $data) || array_key_exists('cover_image', $data)) {
            $updateData['cover_image'] = $data['coverImage'] ?? ($data['cover_image'] ?? null);
        }

        if (! empty($updateData)) {
            $updateData['updated_at'] = Carbon::now();
            DB::table('blog_posts')->where('id', $id)->update($updateData);
        }

        $updated = $this->getById($id, false);

        $oldCover = (string) ($before['coverImage'] ?? '');
        $newCover = (string) ($updated['coverImage'] ?? '');

        if ($oldCover !== '' && $oldCover !== $newCover) {
            $this->deleteManagedImageUrl($oldCover);
        }

        $this->logBlogActivity('BLOG_POST_UPDATED', $updated, [
            'before' => $before,
            'after' => $updated,
        ]);

        return $updated;
    }

    public function delete(int $id): void
    {
        $before = $this->getById($id, false);

        $deleted = DB::table('blog_posts')->where('id', $id)->delete();

        if ($deleted === 0) {
            throw new RuntimeException('Blog post not found.', 404);
        }

        $oldCover = (string) ($before['coverImage'] ?? '');
        if ($oldCover !== '') {
            $this->deleteManagedImageUrl($oldCover);
        }

        $this->logBlogActivity('BLOG_POST_DELETED', $before, ['before' => $before]);
    }

    private function mapRow($row): array
    {
        $rowArray = (array) $row;

        return [
            'id' => (int) $rowArray['id'],
            'title' => (string) ($rowArray['title'] ?? ''),
            'content' => (string) ($rowArray['content'] ?? ''),
            'status' => (string) ($rowArray['status'] ?? ''),
            'coverImage' => $rowArray['cover_image'] ?? null,
            'createdAt' => (string) ($rowArray['created_at'] ?? ''),
            'updatedAt' => (string) ($rowArray['updated_at'] ?? ''),
        ];
    }

    private function validatePayload(array $data): void
    {
        if (empty(trim((string) ($data['title'] ?? '')))) {
            throw new RuntimeException('Blog post title is required.', 422);
        }
        if (empty(trim((string) ($data['content'] ?? '')))) {
            throw new RuntimeException('Blog post content is required.', 422);
        }
    }

    private function deleteManagedImageUrl(string $url): void
    {
        try {
            if (class_exists(UploadStorage::class)) {
                app(UploadStorage::class)->deleteByUrl($url);
            }
        } catch (\Throwable) {
            // Keep CRUD successful even if storage cleanup fails.
        }
    }

    private function logBlogActivity(string $event, array $entity, array $properties = []): void
    {
        try {
            if (function_exists('activity')) {
                $subjectId = (int) ($entity['id'] ?? 0);
                $logger = activity('content')->performedOn(new User(['id' => $subjectId]));

                $actorUserId = $this->resolveActorUserId();
                if ($actorUserId !== null && $actorUserId > 0) {
                    $logger->causedBy(new User(['id' => $actorUserId]));
                }

                if ($properties !== []) {
                    $logger->withProperties($properties);
                }

                $logger->log($event);
            }
        } catch (\Throwable $e) {
            error_log('[BlogPostService] Activity logging failed: '.$e->getMessage());
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
