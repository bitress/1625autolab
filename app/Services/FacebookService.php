<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches posts from the Facebook Graph API using Laravel's Http Facade.
 */
class FacebookService
{
    private string $accessToken;

    private string $graphBase;

    private string $postFields;

    public function __construct()
    {
        $this->accessToken = config('services.facebook.access_token', '');
        $this->graphBase = config('services.facebook.graph_base', 'https://graph.facebook.com/v18.0');
        $this->postFields = config('services.facebook.post_fields', 'id,message,created_time,full_picture,permalink_url');
    }

    /**
     * Retrieve a page of posts from the authenticated Facebook page.
     *
     * @return array{data: list<array<string, mixed>>, paging: array<string, mixed>|null}
     *
     * @throws RuntimeException On network failure or a non-2xx Graph API response.
     */
    public function getPosts(int $limit = 10, ?string $after = null): array
    {
        $params = [
            'fields' => $this->postFields,
            'limit' => $limit,
        ];

        if ($after !== null) {
            $params['after'] = $after;
        }

        try {
            $response = Http::timeout(15)
                ->withToken($this->accessToken)
                ->get($this->graphBase.'/me/posts', $params);

        } catch (ConnectionException $e) {
            $isTimeout = str_contains(strtolower($e->getMessage()), 'timed out')
                || str_contains(strtolower($e->getMessage()), 'timeout');

            if ($isTimeout) {
                throw new RuntimeException(
                    'Request to the Facebook API timed out. Please try again.',
                    504
                );
            }
            throw new RuntimeException(
                'Could not reach the Facebook API. Please try again later.',
                503
            );
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Could not reach the Facebook API. Please try again later.',
                503
            );
        }

        $statusCode = $response->status();
        $payload = $response->json();

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = (is_array($payload) ? ($payload['error']['message'] ?? null) : null)
                ?? 'Failed to fetch Facebook posts.';
            throw new RuntimeException($message, $statusCode);
        }

        return [
            'data' => is_array($payload) ? ($payload['data'] ?? []) : [],
            'paging' => is_array($payload) ? ($payload['paging'] ?? null) : null,
        ];
    }
}
