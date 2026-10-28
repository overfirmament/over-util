<?php

use Overfirmament\OverUtils\Logger\LogFormatter;

/*
 * Log channels used by this package.
 *
 * This file is merged at runtime as `logchannel` and is intentionally NOT
 * published to the application's config directory: `overutil:install` writes
 * these channels into `config/logging.php` instead. The path is relative to the
 * storage directory and is converted with storage_path().
 */
return [
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
];
