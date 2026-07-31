<?php

declare(strict_types=1);

namespace App\Services;

class FacebookService
{
    /**
     * Return recent Facebook page posts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPosts(): array
    {
        throw new \RuntimeException('FacebookService::getPosts() not implemented.', 501);
    }
}
