<?php

return [
    /*
     * Trace field configuration.
     *
     * `field` is the key your middleware/business code writes, e.g. request_id
     * or requestId. Its snake_case/camelCase variants and the legacy trace_id
     * key are always tried as well, so switching this value stays compatible.
     *
     * `output` is the key emitted at the top level of log lines and in API
     * responses by the ApiResponse trait.
     */
    'trace' => [
        'field' => env('OVERUTIL_TRACE_FIELD', 'trace_id'),
        'output' => env('OVERUTIL_TRACE_OUTPUT', 'trace_id'),
    ],

    /*
     * Saloon request/response logging (global middleware).
     *
     * Disabled by default. When enabled it applies to every Saloon connector in
     * the current process unless `connectors` / `except_connectors` filter it.
     */
    'saloon' => [
        'logging' => [
            'enabled' => env('OVERUTIL_SALOON_LOG', false),
            'channel' => env('OVERUTIL_SALOON_LOG_CHANNEL', 'http_out'),
            'level' => env('OVERUTIL_SALOON_LOG_LEVEL', 'info'),

            'log_request' => true,
            'log_response' => true,
            'log_fatal' => true,

            'log_headers' => false,
            'log_body' => true,
            'max_body_length' => 2000,

            'redacted_headers' => [
                'authorization', 'proxy-authorization', 'cookie', 'set-cookie',
                'x-api-key', 'x-auth-token',
            ],
            'redacted_keys' => [
                'password', 'password_confirmation', 'token', 'access_token',
                'refresh_token', 'secret', 'client_secret',
            ],

            // Empty = every connector. Supports wildcards, e.g. App\Integrations\*
            'connectors' => [],
            'except_connectors' => [],
        ],

        'trace' => [
            // Explicit request header that overrides the ambient trace context.
            'header' => env('OVERUTIL_SALOON_TRACE_HEADER'),
            // Add the resolved trace to outgoing requests when the header is absent.
            'propagate' => env('OVERUTIL_SALOON_PROPAGATE_TRACE', true),
        ],
    ],
];
