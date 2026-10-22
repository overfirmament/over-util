<?php

return [
    /*
     * 企业微信机器人配置。
     *
     * 可配置多个机器人，通过 AlarmUtil::init('robot-name') 指定；未指定时使用 default。
     */
    'qy_wx' => [
        'default' => [
            'url' => env('OVERUTIL_ALARM_URL', ''),
            'key' => env('OVERUTIL_ALARM_KEY', ''),
            // 用于报警限流的 Redis 连接
            'connection' => env('OVERUTIL_ALARM_REDIS_CONNECTION', 'default'),
        ],
    ],
];
