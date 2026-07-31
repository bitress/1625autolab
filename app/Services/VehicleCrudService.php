<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * CRUD operations for vehicles saved by a client.
 */
class VehicleCrudService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getByUserId(int $userId): array
    {
        $rows = DB::table('client_vehicles')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return $rows->map(fn ($row) => $this->mapRow((array) $row))->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(int $userId, array $data): array
    {
        $payload = $this->normalizePayload($data);

        $id = DB::table('client_vehicles')->insertGetId([
            'user_id' => $userId,
            'make' => $payload['make'],
            'model' => $payload['model'],
            'year' => $payload['year'],
            'image_url' => $payload['imageUrl'],
            'vin' => $payload['vin'],
            'license_plate' => $payload['licensePlate'],
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return $this->getById($id, $userId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, int $userId, array $data): array
    {
        $current = $this->getById($id, $userId);
        $payload = $this->normalizePayload($data);

        DB::table('client_vehicles')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->update([
                'make' => $payload['make'],
                'model' => $payload['model'],
                'year' => $payload['year'],
                'image_url' => $payload['imageUrl'],
                'vin' => $payload['vin'],
                'license_plate' => $payload['licensePlate'],
                'updated_at' => Carbon::now(),
            ]);

        $oldImage = (string) ($current['imageUrl'] ?? '');
        $newImage = (string) ($payload['imageUrl'] ?? '');
        if ($oldImage !== '' && $oldImage !== $newImage) {
            $this->deleteManagedImageUrl($oldImage);
        }

        return $this->getById($id, $userId);
    }

    public function delete(int $id, int $userId): void
    {
        $current = $this->getById($id, $userId);

        $affected = DB::table('client_vehicles')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete();

        if ($affected === 0) {
            throw new RuntimeException('Vehicle not found.', 404);
        }

        $oldImage = (string) ($current['imageUrl'] ?? '');
        if ($oldImage !== '') {
            $this->deleteManagedImageUrl($oldImage);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getById(int $id, int $userId): array
    {
        $row = DB::table('client_vehicles')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (! $row) {
            throw new RuntimeException('Vehicle not found.', 404);
        }

        return $this->mapRow((array) $row);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{make: string, model: string, year: string, imageUrl: ?string, vin: ?string, licensePlate: ?string}
     */
    private function normalizePayload(array $data): array
    {
        $make = trim((string) ($data['make'] ?? ''));
        $model = trim((string) ($data['model'] ?? ''));
        $year = trim((string) ($data['year'] ?? ''));
        $image = trim((string) ($data['imageUrl'] ?? ($data['image_url'] ?? '')));
        $vin = trim((string) ($data['vin'] ?? ''));
        $plate = trim((string) ($data['licensePlate'] ?? ($data['license_plate'] ?? '')));

        if ($make === '' || $model === '' || $year === '') {
            throw new RuntimeException('make, model, and year are required.', 422);
        }

        if (mb_strlen($year) > 10 || ! preg_match('/^\d{4}$/', $year)) {
            throw new RuntimeException('year must be a 4-digit value.', 422);
        }

        return [
            'make' => mb_substr($make, 0, 120),
            'model' => mb_substr($model, 0, 120),
            'year' => $year,
            'imageUrl' => $image !== '' ? mb_substr($image, 0, 255) : null,
            'vin' => $vin !== '' ? mb_substr($vin, 0, 64) : null,
            'licensePlate' => $plate !== '' ? mb_substr($plate, 0, 32) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'userId' => (int) $row['user_id'],
            'make' => (string) $row['make'],
            'model' => (string) $row['model'],
            'year' => (string) $row['year'],
            'imageUrl' => $row['image_url'] !== null ? (string) $row['image_url'] : null,
            'vin' => $row['vin'] !== null ? (string) $row['vin'] : null,
            'licensePlate' => $row['license_plate'] !== null ? (string) $row['license_plate'] : null,
            'createdAt' => (string) $row['created_at'],
            'updatedAt' => (string) $row['updated_at'],
        ];
    }

    private function deleteManagedImageUrl(string $url): void
    {
        try {
            if (class_exists(UploadStorage::class)) {
                (new UploadStorage)->deleteByUrl($url);
            }
        } catch (Throwable) {
            // Ignore cleanup failures; main CRUD operation already succeeded.
        }
    }
}
