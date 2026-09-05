<?php

use App\Services\Fiscal\FiskalyAustriaProvider;
use App\Services\Fiscal\FiskalyGermanyProvider;

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
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'subscription_webhook_secret' => env('STRIPE_SUBSCRIPTION_WEBHOOK_SECRET'),
    ],

    'vendor_frontend' => [
        'url' => env('VENDOR_FRONTEND_URL', 'http://localhost:3000'),
    ],

    'customer_otp' => [
        // Number of digits in the one-time code.
        'length' => (int) env('CUSTOMER_OTP_LENGTH', 6),
        // How long a code stays valid, in minutes.
        'ttl_minutes' => (int) env('CUSTOMER_OTP_TTL_MINUTES', 10),
        // Maximum wrong verification attempts before a code is burned.
        'max_attempts' => (int) env('CUSTOMER_OTP_MAX_ATTEMPTS', 5),
        // Minimum seconds between two "send" requests for the same email/purpose.
        'resend_cooldown_seconds' => (int) env('CUSTOMER_OTP_RESEND_COOLDOWN', 60),
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
        'customer_enabled' => (bool) env('CUSTOMER_REALTIME_ENABLED', false),
        'queue' => env('REALTIME_QUEUE', 'realtime'),
        'vendor_enabled' => (bool) env('VENDOR_REALTIME_ENABLED', false),
        'vendor_connection' => env('VENDOR_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'redis')),
        'vendor_notifications_queue' => env('VENDOR_NOTIFICATIONS_QUEUE', 'vendornotifications'),
        'vendor_queue' => env('VENDOR_REALTIME_QUEUE', 'vendorrealtime'),
    ],

    'customer_api_cache' => [
        'enabled' => (bool) env('CUSTOMER_API_CACHE_ENABLED', false),
        'store' => env('CUSTOMER_API_CACHE_STORE', 'redis'),
        'ttl' => (int) env('CUSTOMER_API_CACHE_TTL', 120),
    ],

    'customer_commands' => [
        'enabled' => (bool) env('CUSTOMER_ASYNC_COMMANDS_ENABLED', false),
        'connection' => env('CUSTOMER_COMMANDS_CONNECTION', 'redis'),
        'queue' => env('CUSTOMER_COMMANDS_QUEUE', 'customercommands'),
        'status_ttl' => (int) env('CUSTOMER_COMMAND_STATUS_TTL', 3600),
        'barrier_timeout_ms' => (int) env('CUSTOMER_COMMAND_BARRIER_TIMEOUT_MS', 2000),
        // Liveness guard: a running queue worker refreshes a heartbeat every
        // poll. If the newest heartbeat is older than max_age seconds we treat
        // the worker as down and process cart writes synchronously instead of
        // queuing them into a queue nobody is draining. Set max_age to 0 to
        // disable the guard (always queue when enabled).
        'worker_heartbeat_ttl' => (int) env('CUSTOMER_COMMAND_WORKER_HEARTBEAT_TTL', 30),
        'worker_heartbeat_max_age' => (int) env('CUSTOMER_COMMAND_WORKER_MAX_AGE', 15),
    ],

    'staff_commands' => [
        'enabled' => (bool) env('STAFF_ASYNC_COMMANDS_ENABLED', false),
        'connection' => env('STAFF_COMMANDS_CONNECTION', 'redis'),
        'queue' => env('STAFF_COMMANDS_QUEUE', 'staffcommands'),
        'status_ttl' => (int) env('STAFF_COMMAND_STATUS_TTL', 3600),
        'lock_seconds' => (int) env('STAFF_COMMAND_LOCK_SECONDS', 120),
    ],

    'fiskaly' => [
        // Master switch. With this off nothing is signed and no fiskaly call is
        // ever made, whatever a vendor's country is.
        'enabled' => (bool) env('FISKALY_ENABLED', false),

        // 'sandbox' or 'live'. Only used for labelling and for choosing the
        // default base URLs below — the URLs themselves stay overridable.
        'environment' => env('FISKALY_ENVIRONMENT', 'sandbox'),

        'api_key' => env('FISKALY_API_KEY'),
        'api_secret' => env('FISKALY_API_SECRET'),

        // The dashboard key belongs to Tavlo's Group. It is used only to
        // create one managed Unit and one scoped API key per restaurant. The
        // scoped key is encrypted on that restaurant's fiscal_devices row and
        // is what SIGN AT / SIGN DE use afterwards.
        'management' => [
            'base_url' => env('FISKALY_MANAGEMENT_BASE_URL', 'https://dashboard.fiskaly.com/api/v0'),
            'api_key' => env('FISKALY_API_KEY'),
            'api_secret' => env('FISKALY_API_SECRET'),
        ],

        // These specialized APIs use the separate Management API. Newer
        // unified fiskaly products manage Units inside their product API.
        'managed_organization_countries' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('FISKALY_MANAGED_ORGANIZATION_COUNTRIES', 'AT,DE'))
        ))),

        'timeout' => (int) env('FISKALY_TIMEOUT', 15),
        'retries' => (int) env('FISKALY_RETRIES', 3),
        'retry_delay_ms' => (int) env('FISKALY_RETRY_DELAY_MS', 500),

        'queue' => env('FISKALY_QUEUE', 'fiscal'),
        'queue_connection' => env('FISKALY_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),

        // Countries whose orders get fiscalized. A vendor outside this list is
        // served exactly as before — no receipt row, no signature block.
        'countries' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('FISKALY_COUNTRIES', 'AT,DE'))
        ))),

        // Country -> provider class. Adding a country fiskaly supports is a
        // config entry plus one class implementing FiscalProvider; nothing else
        // in the codebase branches on country.
        'providers' => [
            'AT' => FiskalyAustriaProvider::class,
            'DE' => FiskalyGermanyProvider::class,
        ],

        // Countries where registration needs something only the merchant can
        // give us. Austria is the exception: its cash register is registered
        // through the restaurant's own FinanzOnline account, so the vendor has
        // to supply a web-service user. Everywhere else fiskaly provisions from
        // our own API key, so registration runs on admin approval alone.
        'merchant_credential_countries' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('FISKALY_MERCHANT_CREDENTIAL_COUNTRIES', 'AT'))
        ))),

        'at' => [
            // fiskaly SIGN AT (RKSV).
            'base_url' => env('FISKALY_AT_BASE_URL', 'https://rksv.fiskaly.com/api/v1'),
        ],

        'de' => [
            // fiskaly SIGN DE (KassenSichV). Sandbox runs on the middleware
            // host; live is https://kassensichv.fiskaly.com/api/v2.
            'base_url' => env(
                'FISKALY_DE_BASE_URL',
                'https://kassensichv-middleware.fiskaly.com/api/v2'
            ),
        ],

        // Numeric VAT rate -> fiskaly bucket name. Only the standard-rate names
        // are quoted from fiskaly's own examples; verify the rest against the
        // API spec for your contract before going live, and note that an
        // unmapped rate makes fiscalization fail loudly rather than guess.
        'vat_rate_map' => [
            'AT' => [
                '20' => env('FISKALY_AT_VAT_20', 'STANDARD'),
                '10' => env('FISKALY_AT_VAT_10', 'REDUCED_1'),
                '13' => env('FISKALY_AT_VAT_13', 'REDUCED_2'),
                '19' => env('FISKALY_AT_VAT_19', 'SPECIAL'),
                '0' => env('FISKALY_AT_VAT_0', 'ZERO'),
            ],
            'DE' => [
                '19' => env('FISKALY_DE_VAT_19', 'NORMAL'),
                '7' => env('FISKALY_DE_VAT_7', 'REDUCED'),
                '10.7' => env('FISKALY_DE_VAT_10_7', 'AVERAGE_RATE_ONE'),
                '5.5' => env('FISKALY_DE_VAT_5_5', 'AVERAGE_RATE_TWO'),
                '0' => env('FISKALY_DE_VAT_0', 'NULL'),
            ],
        ],

        // Every cent charged must land in a VAT bucket, so these two cannot be
        // left undeclared. Both need confirming with a tax advisor.
        //   service_fee_vat: 'standard' | 'zero' — a service charge is normally
        //     restaurant revenue at the standard rate.
        //   tip_vat: 'standard' | 'zero' — a tip passed on to staff is normally
        //     not taxable revenue; a tip kept by the house is.
        'service_fee_vat' => env('FISKALY_SERVICE_FEE_VAT', 'standard'),
        'tip_vat' => env('FISKALY_TIP_VAT', 'zero'),
    ],

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'jwt_secret' => env('SUPABASE_JWT_SECRET'),
        'realtime_signing_key' => env('SUPABASE_REALTIME_SIGNING_KEY'),
        'realtime_signing_key_id' => env('SUPABASE_REALTIME_SIGNING_KEY_ID'),
        'realtime_token_ttl' => (int) env('SUPABASE_REALTIME_TOKEN_TTL', 900),
    ],

];
