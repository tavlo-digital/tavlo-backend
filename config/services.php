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

    'stripe' => [
        'key'                        => env('STRIPE_KEY'),
        'secret'                     => env('STRIPE_SECRET'),
        'webhook_secret'             => env('STRIPE_WEBHOOK_SECRET'),
        'subscription_webhook_secret' => env('STRIPE_SUBSCRIPTION_WEBHOOK_SECRET'),
    ],

    'vendor_frontend' => [
        'url' => env('VENDOR_FRONTEND_URL', 'http://localhost:3000'),
    ],

    'notifications' => [
        'queue_enabled' => (bool) env('NOTIFICATIONS_QUEUE_ENABLED', false),
        'queue' => env('NOTIFICATIONS_QUEUE', 'notifications'),
    ],

    'session_activity' => [
        'queue_enabled' => (bool) env('SESSION_ACTIVITY_QUEUE_ENABLED', false),
        'queue' => env('SESSION_ACTIVITY_QUEUE', 'activity'),
        'retention_days' => (int) env('SESSION_ACTIVITY_RETENTION_DAYS', 30),
    ],

    'realtime' => [
        'customer_enabled' => (bool) env('CUSTOMER_REVERB_ENABLED', false),
        'queue' => env('REALTIME_QUEUE', 'realtime'),
    ],

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'jwt_secret' => env('SUPABASE_JWT_SECRET'),
        'realtime_signing_key' => env('SUPABASE_REALTIME_SIGNING_KEY'),
        'realtime_signing_key_id' => env('SUPABASE_REALTIME_SIGNING_KEY_ID'),
        'realtime_token_ttl' => (int) env('SUPABASE_REALTIME_TOKEN_TTL', 900),
    ],

];
