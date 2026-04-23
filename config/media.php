<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media signing secret
    |--------------------------------------------------------------------------
    |
    | Shared secret used to sign and verify URLs served by the
    | /media/{path} endpoint. Must match the MEDIA_SECRET used by any
    | trusted client (e.g. the Next.js apps) that needs to generate URLs
    | server-side. Browsers never see this value.
    |
    */
    'secret' => env('MEDIA_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Default URL TTL (seconds)
    |--------------------------------------------------------------------------
    | How long a generated /media/... URL stays valid.
    */
    'ttl' => (int) env('MEDIA_URL_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Public media base URL
    |--------------------------------------------------------------------------
    |
    | Base URL that will be embedded in API responses for media files.
    | Point this at the Next.js proxy (e.g. https://app.example.com) so the
    | browser never talks to Laravel directly for media. If empty, Laravel's
    | own /media/{path} URL is used (fine for local dev without a proxy).
    |
    */
    'public_base_url' => rtrim((string) env('MEDIA_PUBLIC_BASE_URL', ''), '/'),
];
