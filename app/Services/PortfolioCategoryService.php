<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * PortfolioCategoryService
 *
 * Full CRUD for the portfolio_categories table.
 */
class PortfolioCategoryService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * All categories ordered by sort_order, id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $rows = DB::table('portfolio_categories')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return $rows->map(fn ($row) => $this->mapRow((array) $row))->toArray();
    }

    /**
     * Single category by ID.
     *
     * @return array<string, mixed>
     */
    public function getById(int $id): array
    {
        $row = DB::table('portfolio_categories')->where('id', $id)->first();

        if (! $row) {
            throw new RuntimeException('Portfolio category not found.', 404);
        }

        return $this->mapRow((array) $row);
    }

    /**
     * Create a new category. Returns the created record.
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

        $id = DB::table('portfolio_categories')->insertGetId($params);

        return $this->getById($id);
    }

    /**
     * Update an existing category. Returns the updated record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        $current = $this->getById($id);

        $merged = array_merge([
            'name' => $current['name'],
            'sortOrder' => $current['sortOrder'],
        ], $data);

        $params = $this->bindParams($merged);
        $params['updated_at'] = Carbon::now();

        DB::table('portfolio_categories')->where('id', $id)->update($params);

        return $this->getById($id);
    }

    /**
     * Hard-delete a category.
     */
    public function delete(int $id): void
    {
        $affected = DB::table('portfolio_categories')->where('id', $id)->delete();
        if ($affected === 0) {
            throw new RuntimeException('Portfolio category not found.', 404);
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
            'name' => ($row['name'] ?? ''),
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'createdAt' => ($row['created_at'] ?? ''),
            'updatedAt' => ($row['updated_at'] ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private function bindParams(array $data): array
    {
        return [
            'name' => $data['name'] ?? '',
            'sort_order' => (int) ($data['sortOrder'] ?? ($data['sort_order'] ?? 0)),
        ];
    }

    /** @param array<string, mixed> $data */
    private function validatePayload(array $data): void
    {
        if (empty(trim((string) ($data['name'] ?? '')))) {
            throw new RuntimeException('Category name is required.', 422);
        }
    }
}
