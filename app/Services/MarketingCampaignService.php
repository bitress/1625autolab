<?php

declare(strict_types=1);

namespace App\Services;

class MarketingCampaignService
{
    /** @return array<int, array<string, mixed>> */
    public function listCampaigns(): array
    {
        throw new \RuntimeException('MarketingCampaignService::listCampaigns() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getCampaign(int $id): array
    {
        throw new \RuntimeException('MarketingCampaignService::getCampaign() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createCampaign(array $data, ?int $actorId): array
    {
        throw new \RuntimeException('MarketingCampaignService::createCampaign() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateCampaign(int $id, array $data): array
    {
        throw new \RuntimeException('MarketingCampaignService::updateCampaign() not implemented.', 501);
    }

    public function deleteCampaign(int $id): void
    {
        throw new \RuntimeException('MarketingCampaignService::deleteCampaign() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function runCampaign(int $id, bool $dryRun): array
    {
        throw new \RuntimeException('MarketingCampaignService::runCampaign() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function runScheduledDue(int $limit): array
    {
        throw new \RuntimeException('MarketingCampaignService::runScheduledDue() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function analytics(int $id): array
    {
        throw new \RuntimeException('MarketingCampaignService::analytics() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function getAudience(string $type): array
    {
        throw new \RuntimeException('MarketingCampaignService::getAudience() not implemented.', 501);
    }
}
