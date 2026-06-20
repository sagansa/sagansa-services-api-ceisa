<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CEISA 4.0 Gateway Mode
    |--------------------------------------------------------------------------
    | Determines which base URL the CeisaClient will use.
    | Values: 'sandbox' | 'production'
    */
    'gateway_mode' => env('CEISA_GATEWAY_MODE', 'sandbox'),

    'gateways' => [
        'sandbox' => env('CEISA_GATEWAY_SANDBOX', 'https://apisdev-gw.beacukai.go.id'),
        'production' => env('CEISA_GATEWAY_PRODUCTION', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Credentials (fallback if DB ceisa_credentials empty)
    |--------------------------------------------------------------------------
    | In production these are stored encrypted in the `ceisa_credentials` table.
    */
    'application_id' => env('CEISA_APPLICATION_ID', ''),
    'api_key' => env('CEISA_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    */
    'webhook_url' => env('CEISA_WEBHOOK_URL', 'https://api-ceisa.sagansa.id/v1/ceisa-webhook'),
    'webhook_secret' => env('CEISA_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | HTTP client & retry tuning (NFR: retry every 5 minutes, max 3x)
    |--------------------------------------------------------------------------
    */
    'http_timeout' => (int) env('CEISA_HTTP_TIMEOUT', 30),
    'connect_timeout' => (int) env('CEISA_CONNECT_TIMEOUT', 10),
    'submit_tries' => (int) env('CEISA_SUBMIT_TRIES', 3),
    'submit_backoff' => (int) env('CEISA_SUBMIT_BACKOFF', 300),

    /*
    |--------------------------------------------------------------------------
    | Test/health path untuk cek koneksi gateway (Fase 2)
    |--------------------------------------------------------------------------
    | Path yang di-hit oleh CeisaClient::ping(). WAJIB berupa path API nyata
    | (bukan '/') karena gateway menutup koneksi tanpa respons HTTP untuk root.
    | Setiap HTTP response code (termasuk 401/403/404) dianggap "reachable".
    */
    'test_path' => env('CEISA_TEST_PATH', '/v1/pib/submit'),

    /*
    |--------------------------------------------------------------------------
    | Status → urgency classification (PRD 2.2)
    |--------------------------------------------------------------------------
    */
    'urgency' => [
        'normal' => ['AJU', 'HIJAU', 'SPPB'],
        'urgent' => ['MERAH', 'NOTUL', 'SPTNP', 'DENDA', 'SSP', 'SPP'],
    ],
];