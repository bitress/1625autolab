<?php

declare(strict_types=1);

namespace App\Services;

class PrivacyService
{
    /**
     * Export all data associated with a user (GDPR Subject Access Request).
     *
     * @return array<string, mixed>
     */
    public function exportData(int $userId): array
    {
        throw new \RuntimeException('PrivacyService::exportData() not implemented.', 501);
    }

    /**
     * Permanently delete a user account and all associated data.
     */
    public function deleteAccount(int $userId): void
    {
        throw new \RuntimeException('PrivacyService::deleteAccount() not implemented.', 501);
    }
}
