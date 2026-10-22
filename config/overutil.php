<?php

use Overfirmament\OverUtils\Logger\LogFormatter;

return [
    /*
     * Log channels used by this package.
     *
     * These values are merged into `logging.channels` at runtime when the
     * corresponding channel is not defined by the application. The path is
     * relative to the storage directory and is converted with storage_path().
     */
    'logging' => [
        'channels' => [
            'http_out' => [
                'driver' => 'daily',
                'path' => 'logs/http/out_info.log',
                'level' => env('LOG_LEVEL', 'debug'),
                'days' => 14,
                'formatter' => LogFormatter::class,
            ],
            'http_in' => [
                'driver' => 'daily',
                'path' => 'logs/http/in_info.log',
                'level' => env('LOG_LEVEL', 'debug'),
                'days' => 14,
                'formatter' => LogFormatter::class,
            ],
            'rabbit_mq' => [
                'driver' => 'daily',
                'path' => 'logs/rabbit/debug.log',
                'level' => env('LOG_LEVEL', 'debug'),
                'days' => 14,
                'formatter' => LogFormatter::class,
            ],
        ],
    ],
];
