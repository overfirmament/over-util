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
];
