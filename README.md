# over-util

个人使用的工具包

安装本包后，如果无法自动复制配置，请在 `config/logging.php` 文件中加入两项日志 channels:
```php
    'http_out' => [
            'driver' => 'daily',
            'path' => storage_path('logs/http_out/info.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'formatter' => Overfirmament\OverUtils\Logger\LogFormatter::class
        ],

    "http_in" => [
        'driver' => 'daily',
        'path' => storage_path('logs/http_in/info.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
        'formatter' => Overfirmament\OverUtils\Logger\LogFormatter::class
    ],
```
