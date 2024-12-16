<?php

return [
    "channels" => [
        "http_in" => [
            "driver" => "daily",
            "path" => storage_path("logs/http/in_info.log"),
            "level" => env("LOG_LEVEL", "debug"),
            "days" => 14,
            "formatter" => \Overfirmament\OverUtils\Logger\LogFormatter::class
        ],
        "http_out" => [
            "driver" => "daily",
            "path" => storage_path("logs/http/out_info.log"),
            "level" => env("LOG_LEVEL", "debug"),
            "days" => 14,
            "formatter" => \Overfirmament\OverUtils\Logger\LogFormatter::class
        ]
    ]
];