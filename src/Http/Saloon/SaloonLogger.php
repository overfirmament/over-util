<?php

namespace Overfirmament\OverUtils\Http\Saloon;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonSerializable;
use Overfirmament\OverUtils\Support\TraceContext;
use Psr\Http\Message\StreamInterface;
use Saloon\Data\MultipartValue;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Saloon\Repositories\Body\MultipartBodyRepository;
use Stringable;
use Throwable;

/**
 * Builds and writes Saloon request/response log records.
 *
 * The middleware only decides *when* to log; formatting, filtering, trace
 * resolution and redaction all live here so both pipelines stay consistent.
 */
final class SaloonLogger
{
    public const STARTED_AT_KEY = 'overutil.saloon.started_at';

    public const TRACE_KEY = 'overutil.saloon.trace';

    private const REDACTED = '***';

    /**
     * @var array<int, string>
     */
    private const LEVELS = [
        'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency',
    ];

    public static function enabled(): bool
    {
        return (bool) config('overutil.saloon.logging.enabled', false);
    }

    public static function logRequestEnabled(): bool
    {
        return (bool) config('overutil.saloon.logging.log_request', true);
    }

    public static function logResponseEnabled(): bool
    {
        return (bool) config('overutil.saloon.logging.log_response', true);
    }

    public static function logFatalEnabled(): bool
    {
        return (bool) config('overutil.saloon.logging.log_fatal', true);
    }

    /**
     * Apply the connector allow/deny lists.
     */
    public static function shouldLog(PendingRequest $pendingRequest): bool
    {
        $connector = $pendingRequest->getConnector()::class;

        foreach (self::patterns('except_connectors') as $pattern) {
            if (Str::is($pattern, $connector)) {
                return false;
            }
        }

        $connectors = self::patterns('connectors');

        if ($connectors === []) {
            return true;
        }

        foreach ($connectors as $pattern) {
            if (Str::is($pattern, $connector)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record the start time and resolve/propagate the trace id once per request.
     */
    public static function prepare(PendingRequest $pendingRequest): void
    {
        $pendingRequest->config()->add(self::STARTED_AT_KEY, microtime(true));

        $trace = self::resolveTrace($pendingRequest);

        if ($trace === '') {
            return;
        }

        $pendingRequest->config()->add(self::TRACE_KEY, $trace);

        self::propagate($pendingRequest, $trace);
    }

    /**
     * Prefer an explicit request header, then fall back to the ambient context.
     */
    public static function resolveTrace(PendingRequest $pendingRequest): string
    {
        $header = config('overutil.saloon.trace.header');

        if (is_string($header) && $header !== '') {
            $value = self::headerValue($pendingRequest->headers()->all(), $header);

            if ($value !== '') {
                return $value;
            }
        }

        return TraceContext::resolve();
    }

    /**
     * Inject the trace header into the outgoing request unless it is already set.
     */
    public static function propagate(PendingRequest $pendingRequest, string $trace): void
    {
        $header = config('overutil.saloon.trace.header');

        if (! is_string($header) || $header === '') {
            return;
        }

        if (! (bool) config('overutil.saloon.trace.propagate', true)) {
            return;
        }

        if (self::headerValue($pendingRequest->headers()->all(), $header) !== '') {
            return;
        }

        $pendingRequest->headers()->add($header, $trace);
    }

    public static function recordRequest(PendingRequest $pendingRequest): void
    {
        $context = [
            'method' => $pendingRequest->getMethod()->value,
            'url' => self::url($pendingRequest),
            'connector' => $pendingRequest->getConnector()::class,
            'request' => $pendingRequest->getRequest()::class,
        ];

        $context = self::withOptionalData(
            $context,
            $pendingRequest->headers()->all(),
            self::requestBody($pendingRequest)
        );

        self::write('saloon.request', self::withTrace($context, $pendingRequest));
    }

    public static function recordResponse(Response $response): void
    {
        $pendingRequest = $response->getPendingRequest();

        $context = [
            'method' => $pendingRequest->getMethod()->value,
            'url' => self::url($pendingRequest),
            'status' => $response->status(),
            'duration_ms' => self::duration($pendingRequest),
            'connector' => $pendingRequest->getConnector()::class,
            'request' => $pendingRequest->getRequest()::class,
        ];

        if (self::boolConfig('log_headers')) {
            $context['headers'] = self::redactHeaders($response->headers()->all());
        }

        if (self::boolConfig('log_body', true)) {
            $body = self::responseBody($response);

            if ($body !== null) {
                $context['body'] = $body;
            }
        }

        self::write('saloon.response', self::withTrace($context, $pendingRequest));
    }

    public static function recordFatal(FatalRequestException $exception): void
    {
        $pendingRequest = $exception->getPendingRequest();

        $context = [
            'method' => $pendingRequest->getMethod()->value,
            'url' => self::url($pendingRequest),
            'exception' => $exception->getPrevious() !== null
                ? $exception->getPrevious()::class
                : $exception::class,
            'message' => $exception->getMessage(),
            'duration_ms' => self::duration($pendingRequest),
            'connector' => $pendingRequest->getConnector()::class,
            'request' => $pendingRequest->getRequest()::class,
        ];

        self::write('saloon.fatal', self::withTrace($context, $pendingRequest), 'error');
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function withOptionalData(array $context, array $headers, mixed $body): array
    {
        if (self::boolConfig('log_headers')) {
            $context['headers'] = self::redactHeaders($headers);
        }

        if (self::boolConfig('log_body', true) && $body !== null) {
            $context['body'] = self::prepareBody($body);
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function withTrace(array $context, PendingRequest $pendingRequest): array
    {
        $trace = self::traceFor($pendingRequest);

        if ($trace !== '') {
            $context[TraceContext::output()] = $trace;
        }

        return $context;
    }

    private static function traceFor(PendingRequest $pendingRequest): string
    {
        $stored = $pendingRequest->config()->get(self::TRACE_KEY);

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        return self::resolveTrace($pendingRequest);
    }

    private static function requestBody(PendingRequest $pendingRequest): mixed
    {
        $body = $pendingRequest->body();

        if ($body === null || $body->isEmpty()) {
            return null;
        }

        if ($body instanceof MultipartBodyRepository) {
            return array_map(
                static fn (MultipartValue $value): array => array_filter([
                    'name' => $value->name,
                    'filename' => $value->filename,
                ], static fn (mixed $item): bool => $item !== null),
                $body->all()
            );
        }

        return $body->all();
    }

    private static function responseBody(Response $response): mixed
    {
        $contentType = (string) ($response->headers()->get('Content-Type') ?? '');
        $mime = strtolower(trim(explode(';', $contentType)[0]));

        if (! self::isTextualMime($mime)) {
            $size = $response->getPsrResponse()->getBody()->getSize();

            return '[binary omitted: '.($mime !== '' ? $mime : 'unknown').', '.($size ?? 0).' bytes]';
        }

        return self::prepareBody($response->body());
    }

    private static function isTextualMime(string $mime): bool
    {
        if ($mime === '') {
            return true;
        }

        foreach (['json', 'text/', 'xml', 'javascript', 'x-www-form-urlencoded', 'graphql'] as $needle) {
            if (str_contains($mime, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function prepareBody(mixed $value): mixed
    {
        $value = self::sanitize($value);

        if (is_string($value)) {
            if (trim($value) === '') {
                return null;
            }

            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            return self::truncateJson(self::redactArray($value));
        }

        if (is_string($value)) {
            if (! mb_check_encoding($value, 'UTF-8')) {
                return '[binary omitted: '.strlen($value).' bytes]';
            }

            return self::truncateString($value);
        }

        return is_scalar($value) ? $value : null;
    }

    private static function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::sanitize($item), $value);
        }

        if (is_resource($value)) {
            return '[resource omitted]';
        }

        if ($value instanceof StreamInterface) {
            return '[stream omitted]';
        }

        if ($value instanceof Arrayable) {
            return self::sanitize($value->toArray());
        }

        if ($value instanceof JsonSerializable) {
            return self::sanitize($value->jsonSerialize());
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_object($value)) {
            return '[object '.$value::class.']';
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function redactArray(array $data): array
    {
        $keys = self::lowerStringList('redacted_keys');

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $keys, true)) {
                $data[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redactArray($value);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private static function redactHeaders(array $headers): array
    {
        $keys = self::lowerStringList('redacted_headers');
        $result = [];

        foreach ($headers as $key => $value) {
            $result[$key] = in_array(strtolower((string) $key), $keys, true)
                ? self::REDACTED
                : self::sanitize($value);
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function truncateJson(array $data): array
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $limit = self::maxBodyLength();

        if ($json === false || $limit <= 0 || strlen($json) <= $limit) {
            return $data;
        }

        return [
            '_truncated' => true,
            'bytes' => strlen($json),
            'preview' => self::cut($json, $limit).'...',
        ];
    }

    private static function truncateString(string $value): string
    {
        $limit = self::maxBodyLength();

        if ($limit <= 0 || strlen($value) <= $limit) {
            return $value;
        }

        return self::cut($value, $limit).'... [truncated, original '.strlen($value).' bytes]';
    }

    private static function cut(string $value, int $limit): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return mb_substr($value, 0, $limit);
        }

        return substr($value, 0, $limit);
    }

    private static function duration(PendingRequest $pendingRequest): ?float
    {
        $startedAt = $pendingRequest->config()->get(self::STARTED_AT_KEY);

        if (! is_numeric($startedAt)) {
            return null;
        }

        return round((microtime(true) - (float) $startedAt) * 1000, 2);
    }

    private static function url(PendingRequest $pendingRequest): string
    {
        $url = $pendingRequest->getUrl();
        $query = $pendingRequest->query()->all();

        if ($query === []) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private static function headerValue(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) !== 0) {
                continue;
            }

            if (is_array($value)) {
                $value = reset($value);
            }

            return is_scalar($value) ? trim((string) $value) : '';
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private static function patterns(string $key): array
    {
        return self::stringList($key);
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(string $key): array
    {
        $value = config("overutil.saloon.logging.{$key}", []);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $item): string => (string) $item,
            array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== '')
        ));
    }

    /**
     * @return array<int, string>
     */
    private static function lowerStringList(string $key): array
    {
        return array_map('strtolower', self::stringList($key));
    }

    private static function boolConfig(string $key, bool $default = false): bool
    {
        return (bool) config("overutil.saloon.logging.{$key}", $default);
    }

    private static function maxBodyLength(): int
    {
        return max(0, (int) config('overutil.saloon.logging.max_body_length', 2000));
    }

    private static function level(): string
    {
        $level = config('overutil.saloon.logging.level', 'info');
        $level = is_string($level) ? strtolower($level) : 'info';

        return in_array($level, self::LEVELS, true) ? $level : 'info';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function write(string $message, array $context, ?string $level = null): void
    {
        $level ??= self::level();
        $channel = config('overutil.saloon.logging.channel');

        try {
            if (is_string($channel) && $channel !== '') {
                Log::channel($channel)->log($level, $message, $context);

                return;
            }

            Log::log($level, $message, $context);
        } catch (Throwable) {
            try {
                Log::log($level, $message, $context);
            } catch (Throwable) {
                // Logging must never break the outgoing HTTP request.
            }
        }
    }
}
