<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| The API uses Sanctum bearer tokens (no cookies), so credentialed CORS is
| not needed. Flutter apps ignore CORS entirely; browser frontends (Nuxt)
| need their origin allowed here. Override per environment:
|
|   CORS_ALLOWED_ORIGINS=https://app.your-domain.com,https://admin.your-domain.com
|
| The default '*' is fine while supports_credentials stays false.
|
*/

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
