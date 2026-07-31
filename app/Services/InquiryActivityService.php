<?php

declare(strict_types=1);

namespace App\Services;

class InquiryActivityService
{
    /** @return array<string, mixed> */
    public function getForInquiry(string $inquiryId): array
    {
        throw new \RuntimeException('InquiryActivityService::getForInquiry() not implemented.', 501);
    }
}
