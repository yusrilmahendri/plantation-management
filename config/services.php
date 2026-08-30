<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'finance' => [
        'service_token' => env('FINANCE_SERVICE_TOKEN'),
        'base_url' => env('FINANCE_SERVICE_URL'),
        'timeout' => (int) env('FINANCE_SERVICE_TIMEOUT', 15),
        'hmac_secret' => env('FINANCE_HMAC_SECRET'),
    ],

    'integration' => [
        'events_enabled' => (bool) env('INTEGRATION_EVENTS_ENABLED', false),
        'event_version' => 1,
        'queue' => env('INTEGRATION_QUEUE', 'integrations'),
        'max_attempts' => (int) env('INTEGRATION_MAX_ATTEMPTS', 8),
        'backoff' => [60, 300, 900, 3600],
        'outbox_retention_days' => (int) env('INTEGRATION_OUTBOX_RETENTION_DAYS', 90),
    ],

];
