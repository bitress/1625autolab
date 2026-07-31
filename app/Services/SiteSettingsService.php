<?php

declare(strict_types=1);

namespace App\Services;

class SiteSettingsService
{
    /** @return array<string, mixed> */
    public function getAll(): array
    {
        throw new \RuntimeException('SiteSettingsService::getAll() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(array $data): array
    {
        throw new \RuntimeException('SiteSettingsService::update() not implemented.', 501);
    }
}
