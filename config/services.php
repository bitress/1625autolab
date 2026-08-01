<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'turnstile' => [
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'bypass' => env('TURNSTILE_BYPASS', false),
    ],

    'semaphore' => [
        'api_key' => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME', '1625AutoLab'),
        'account_cache_ttl' => env('SEMAPHORE_ACCOUNT_CACHE_TTL', 60),
        'messages_cache_ttl' => env('SEMAPHORE_MESSAGES_CACHE_TTL', 30),
    ],

    'facebook' => [
        'access_token' => env('FB_ACCESS_TOKEN'),
        'graph_base' => env('FB_GRAPH_BASE', 'https://graph.facebook.com/v18.0'),
    ],

];
