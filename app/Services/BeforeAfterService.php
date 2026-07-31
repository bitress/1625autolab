<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BeforeAfterService
{
    public function getById(int $id, bool $requireActive = true): array
    {
        $query = DB::table('before_after_items')->where('id', $id);

        if ($requireActive) {
            $query->where('is_active', 1);
        }

        $row = $query->first();

        if (! $row) {
            throw new RuntimeException('Before/After item not found.', 404);
        }

        return $this->mapRow((array) $row);
    }

    public function create(array $data): array
    {
        $this->validatePayload($data, true);

        $id = DB::table('before_after_items')->insertGetId([
            'title' => trim((string) ($data['title'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'vehicle_make' => trim((string) ($data['vehicleMake'] ?? '')),
            'vehicle_model' => trim((string) ($data['vehicleModel'] ?? '')),
            'before_image_url' => trim((string) ($data['beforeImageUrl'] ?? '')),
            'after_image_url' => trim((string) ($data['afterImageUrl'] ?? '')),
            'is_active' => ! empty($data['isActive']) ? 1 : 0,
            'sort_order' => (int) ($data['sortOrder'] ?? 0),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return $this->getById($id, false);
    }

    public function update(int $id, array $data): array
    {
        $this->validatePayload($data, false);

        // Ensure it exists
        $this->getById($id, false);

        $updateData = [];

        if (array_key_exists('title', $data)) {
            $updateData['title'] = trim((string) $data['title']);
        }
        if (array_key_exists('description', $data)) {
            $updateData['description'] = trim((string) $data['description']);
        }
        if (array_key_exists('vehicleMake', $data)) {
            $updateData['vehicle_make'] = trim((string) $data['vehicleMake']);
        }
        if (array_key_exists('vehicleModel', $data)) {
            $updateData['vehicle_model'] = trim((string) $data['vehicleModel']);
        }
        if (array_key_exists('beforeImageUrl', $data)) {
            $updateData['before_image_url'] = trim((string) $data['beforeImageUrl']);
        }
        if (array_key_exists('afterImageUrl', $data)) {
            $updateData['after_image_url'] = trim((string) $data['afterImageUrl']);
        }
        if (array_key_exists('isActive', $data)) {
            $updateData['is_active'] = ! empty($data['isActive']) ? 1 : 0;
        }
        if (array_key_exists('sortOrder', $data)) {
            $updateData['sort_order'] = (int) $data['sortOrder'];
        }

        if (! empty($updateData)) {
            $updateData['updated_at'] = Carbon::now();
            DB::table('before_after_items')->where('id', $id)->update($updateData);
        }

        return $this->getById($id, false);
    }

    public function delete(int $id): void
    {
        $deleted = DB::table('before_after_items')->where('id', $id)->delete();

        if ($deleted === 0) {
            throw new RuntimeException('Before/After item not found.', 404);
        }
    }

    public function getAll(bool $includeInactive = false, string $vehicleMake = '', string $vehicleModel = ''): array
    {
        $query = DB::table('before_after_items');

        if (! $includeInactive) {
            $query->where('is_active', 1);
        }

        if ($vehicleMake !== '') {
            $query->whereRaw('LOWER(vehicle_make) LIKE ?', ['%'.strtolower($vehicleMake).'%']);
        }

        if ($vehicleModel !== '') {
            $query->whereRaw('LOWER(vehicle_model) LIKE ?', ['%'.strtolower($vehicleModel).'%']);
        }

        $rows = $query->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return array_map([$this, 'mapRow'], $rows->toArray());
    }

    private function mapRow($row): array
    {
        $rowArray = (array) $row;

        return [
            'id' => (int) $rowArray['id'],
            'title' => (string) ($rowArray['title'] ?? ''),
            'description' => (string) ($rowArray['description'] ?? ''),
            'vehicleMake' => (string) ($rowArray['vehicle_make'] ?? ''),
            'vehicleModel' => (string) ($rowArray['vehicle_model'] ?? ''),
            'beforeImageUrl' => (string) ($rowArray['before_image_url'] ?? ''),
            'afterImageUrl' => (string) ($rowArray['after_image_url'] ?? ''),
            'isActive' => (bool) ($rowArray['is_active'] ?? 1),
            'sortOrder' => (int) ($rowArray['sort_order'] ?? 0),
            'createdAt' => (string) ($rowArray['created_at'] ?? ''),
            'updatedAt' => (string) ($rowArray['updated_at'] ?? ''),
        ];
    }

    private function validatePayload(array $data, bool $isCreate): void
    {
        if ($isCreate || array_key_exists('title', $data)) {
            $title = trim((string) ($data['title'] ?? ''));
            if ($title === '') {
                throw new RuntimeException('Field "title" is required.', 422);
            }
        }

        if ($isCreate || array_key_exists('description', $data)) {
            $description = trim((string) ($data['description'] ?? ''));
            if ($description === '') {
                throw new RuntimeException('Field "description" is required.', 422);
            }
        }

        if ($isCreate || array_key_exists('beforeImageUrl', $data)) {
            $before = trim((string) ($data['beforeImageUrl'] ?? ''));
            if ($before === '') {
                throw new RuntimeException('Field "beforeImageUrl" is required.', 422);
            }
        }

        if ($isCreate || array_key_exists('afterImageUrl', $data)) {
            $after = trim((string) ($data['afterImageUrl'] ?? ''));
            if ($after === '') {
                throw new RuntimeException('Field "afterImageUrl" is required.', 422);
            }
        }
    }
}
