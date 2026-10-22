<?php

return [
    /*
     * RabbitMQ 连接配置。
     *
     * 每个连接包含 exchange / queue / routing_key / message / publish / consumer
     * 六个部分，供 RabbitMqUtil 使用。
     */
    'connections' => [
        'default' => [
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => (int) env('RABBITMQ_PORT', 5672),
            'username' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),

            'connection_timeout' => 3.0,
            // 需要大于 heartbeat 的两倍，避免长连接被客户端提前判定超时
            'read_write_timeout' => 60.0,
            'keepalive' => false,
            'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 30),
            'channel_rpc_timeout' => 0.0,

            'exchange' => [
                'name' => env('RABBITMQ_EXCHANGE', 'over-util'),
                'type' => env('RABBITMQ_EXCHANGE_TYPE', 'direct'),
                'passive' => false,
                'durable' => true,
                'auto_delete' => false,
                'internal' => false,
                'nowait' => false,
                'arguments' => [],
                'ticket' => null,
            ],

            'queue' => [
                'name' => env('RABBITMQ_QUEUE', 'over-util'),
                'passive' => false,
                'durable' => true,
                'exclusive' => false,
                'auto_delete' => false,
                'nowait' => false,
                'arguments' => [],
                'ticket' => null,
            ],

            'routing_key' => env('RABBITMQ_ROUTING_KEY', ''),

            'message' => [
                'properties' => [
                    'content_type' => 'application/json',
                    'delivery_mode' => 2,
                ],
            ],

            'publish' => [
                'mandatory' => false,
                'immediate' => false,
                'ticket' => null,
                // 等待 broker confirm 的最长秒数，避免连接异常时无限等待
                'confirm_timeout' => 5.0,
                'close_connection_after_publish' => false,
            ],

            'consumer' => [
                'tag' => '',
                'no_local' => false,
                'no_ack' => false,
                'exclusive' => false,
                'nowait' => false,
                'ticket' => null,
                'arguments' => [],
            ],
        ],
    ],
];
