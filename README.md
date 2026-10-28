# over-util

个人使用的 Laravel 工具包。

## 环境要求

- PHP 8.2 - 8.5
- Laravel 12 或 13（PHP 8.2 使用 Laravel 12；Laravel 13 需要 PHP 8.3+）
- PHP 扩展：`dom`、`iconv`、`json`、`mbstring`、`sockets`、`zlib`

## 安装

```bash
composer require overfirmament/over-util
php artisan overutil:install
```

`overutil:install` 会完成以下工作：

- 发布 `config/overutil.php`、`config/alarm.php`、`config/rabbitmq.php`
- 在 `config/logging.php` 的 `channels` 中写入 `http_out`、`http_in`、`rabbit_mq`
- 向 `app/Providers/AppServiceProvider.php` 的 `boot()` 注入默认注释的 `WorkerStopping` 示例
- 清理配置缓存

已存在的配置文件和日志 channel 默认跳过；使用 `--force` 覆盖：

```bash
php artisan overutil:install --force
```

只查看将要修改的内容、不写入文件：

```bash
php artisan overutil:install --dry-run
```

修改前会在 `config/logging.php` 旁生成 `logging.php.overutil.bak` 备份。向 `AppServiceProvider.php` 注入内容前也会生成 `AppServiceProvider.php.overutil.bak` 备份。

日志 channel 的定义位于包内部的 `config/logchannel.php`，该文件不会发布到应用：安装命令只把它们写入 `config/logging.php`。即使没有执行安装命令，ServiceProvider 也会在运行时补充缺失的 channel。

## Horizon / 常驻 worker

安装命令会在 `app/Providers/AppServiceProvider.php` 的 `boot()` 中注入一段默认注释的示例代码：

```php
// over-util: rabbitmq-worker-stopping
// 可选：Horizon/队列 worker 停止时关闭 RabbitMQ 共享连接。
// 确认需要后，取消下面代码的注释即可启用。
// \Illuminate\Support\Facades\Event::listen(
//     \Illuminate\Queue\Events\WorkerStopping::class,
//     static function (\Illuminate\Queue\Events\WorkerStopping $event): void {
//         app(\Overfirmament\OverUtils\ToolBox\RabbitMqConnectionManager::class)->disconnect();
//     }
// );
```

默认不会启用。Horizon 等常驻 worker 如果需要优雅退出时关闭共享连接，取消注释即可；只关闭指定连接时，把 `disconnect()` 改为 `disconnect('thread_update')`。安装命令会根据 `over-util: rabbitmq-worker-stopping` 标记幂等跳过，不会重复注入或覆盖已经修改过的代码。

## 日志

安装命令写入的 channel 如下：

```php
'http_out' => [
    'driver' => 'daily',
    'path' => storage_path('logs/http/out_info.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 14,
    'formatter' => \Overfirmament\OverUtils\Logger\LogFormatter::class,
],

'http_in' => [
    'driver' => 'daily',
    'path' => storage_path('logs/http/in_info.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 14,
    'formatter' => \Overfirmament\OverUtils\Logger\LogFormatter::class,
],

'rabbit_mq' => [
    'driver' => 'daily',
    'path' => storage_path('logs/rabbit/debug.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 14,
    'formatter' => \Overfirmament\OverUtils\Logger\LogFormatter::class,
],
```

`LogFormatter` 会自动把追踪字段提到 JSON 顶层，并对 GBK 字符串做 UTF-8 转换，避免日志写入失败。

## 追踪字段

追踪字段统一由 `Overfirmament\OverUtils\Support\TraceContext` 解析，`LogFormatter`、`HelperUtil::getContextTraceId()`、`ApiResponse`、`AlarmUtil` 都通过它读取，不再各自硬编码 `trace_id`。

在 `config/overutil.php`（或 `.env`）中配置：

```php
'trace' => [
    // 中间件/业务代码写入的字段，如 request_id、requestId
    'field'  => env('OVERUTIL_TRACE_FIELD', 'trace_id'),
    // 日志与 API 响应中输出的字段
    'output' => env('OVERUTIL_TRACE_OUTPUT', 'trace_id'),
],
```

例如项目使用 `$request->merge(['request_id' => $uuid])`，只需把 `field` 配成 `request_id`，日志即可取到追踪号。

取值顺序（单条日志优先）：

1. 单条日志的 `context`
2. 单条日志的 `extra`
3. Laravel `Context`
4. `Log::sharedContext()`
5. 当前请求的 input / 路由参数（非 console 环境）

候选键始终是联合的：配置字段、它的 snake_case/camelCase 变体，以及兼容保留的 `trace_id` / `traceId`。因此切换配置不会影响历史日志的解析。

自定义 formatter 时可覆盖字段：

```php
$formatter = new \Overfirmament\OverUtils\Logger\LogFormatter;
$formatter->setTrackField('requestId')->setOutputField('request_id');
```

## API 响应

`ApiResponse` trait 支持业务码与 HTTP 状态码分离：

```php
use Overfirmament\OverUtils\Traits\ApiResponse;

class Controller
{
    use ApiResponse;

    public function show()
    {
        return $this->success(['id' => 1]);       // HTTP 200
        // return $this->created($data);          // HTTP 201
        // return $this->deleted();               // HTTP 204
        // return $this->notFound();              // HTTP 404
        // return $this->failed('失败', 1001);     // HTTP 400，业务码 1001
    }
}
```

### JWT / Token 过期时间

`respondWithToken()` 的第三个参数支持秒数、`DateTimeInterface`、`DateInterval`。不传时会自动尝试解析当前 guard 的过期时间：

```php
return $this->respondWithToken($token);
```

需要指定 guard 时：

```php
return $this->respondWithToken($token, 'Bearer', null, 'api');
```

兼容 `tymon/jwt-auth` 的 `auth()->guard('api')->factory()->getTTL()`（单位：分钟），也支持 Sanctum、Passport 的配置回退。包本身不依赖 `tymon/jwt-auth`。

也可以显式传入秒数：

```php
return $this->respondWithToken(
    $token,
    'Bearer',
    auth()->guard('api')->factory()->getTTL() * 60
);
```

## RabbitMQ

配置文件 `config/rabbitmq.php` 支持多个连接，默认使用 `default` 连接。典型的循环发送写法：

```php
use Overfirmament\OverUtils\ToolBox\RabbitMqUtil;

foreach ($messages as $message) {
    RabbitMqUtil::getInstance()
        ->autoBuild()
        ->message(json_encode($message))
        ->publish();
}
```

`getInstance()` 每次返回新的轻量对象，底层 `AMQPStreamConnection` 由 `RabbitMqConnectionManager` 按连接名缓存复用。`publish()` 只关闭当前 channel，不会关闭共享连接，因此可以在外部 `foreach` 中安全使用。

需要主动释放连接时：

```php
$mq = RabbitMqUtil::getInstance();
// ...
$mq->closeConnection();
```

也可以设置：

```php
'publish' => [
    // ...
    'close_connection_after_publish' => true,
],
```

## 企业微信报警

```php
use Overfirmament\OverUtils\ToolBox\AlarmUtil;

AlarmUtil::init() // 默认读取 alarm.qy_wx.default
    ->text('标题', '内容');
```

可配置多个机器人：

```php
AlarmUtil::init('ops')->text('告警', '服务异常');
```

需要限流时：

```php
AlarmUtil::init()
    ->setLimit(3, 'order-sync', 300)
    ->text('告警', '订单同步失败');
```

## 常用命令

```bash
php artisan overutil:cache-reset   # 清理路由缓存并重建
php artisan publish:cache          # 兼容旧名称
```

## 开发

```bash
composer test          # PHPUnit
composer analyse       # PHPStan + Larastan
composer format:test   # Pint 检查
vendor/bin/phpcs       # PHPCompatibility 检查
```
