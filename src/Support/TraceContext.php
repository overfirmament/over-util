<?php

namespace Overfirmament\OverUtils\Support;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stringable;
use Throwable;

/**
 * Central resolver for the trace identifier.
 *
 * Every part of the package (log formatter, API responses, helpers, alarms)
 * reads the trace value through this class so that a consumer can freely pick
 * the field used by its own middleware, e.g. `request_id` or `requestId`.
 *
 * The configured field is never used alone: its snake_case/camelCase variants
 * and the legacy `trace_id`/`traceId` keys are always tried, so changing the
 * configuration does not break existing applications.
 */
final class TraceContext
{
    public const DEFAULT_FIELD = 'trace_id';

    public const DEFAULT_OUTPUT = 'trace_id';

    /**
     * The configured source field name (e.g. `request_id`).
     */
    public static function field(): string
    {
        return self::configString('overutil.trace.field', self::DEFAULT_FIELD);
    }

    /**
     * The field name emitted in logs and API responses.
     */
    public static function output(): string
    {
        return self::configString('overutil.trace.output', self::DEFAULT_OUTPUT);
    }

    /**
     * All keys that may carry the trace value, in lookup order.
     *
     * @return array<int, string>
     */
    public static function candidates(?string $field = null): array
    {
        $field = $field ?? self::field();

        return array_values(array_unique(array_filter([
            $field,
            Str::snake($field),
            Str::camel($field),
            'trace_id',
            'traceId',
        ], static fn (mixed $key): bool => is_string($key) && $key !== '')));
    }

    /**
     * Resolve the trace id from the given record data, then from the framework
     * context and finally from the current request.
     *
     * Explicit log data wins over shared context, so a single log call can
     * always override the value injected by a middleware.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $extra
     */
    public static function resolve(array $context = [], array $extra = [], ?string $field = null): string
    {
        $field ??= self::field();

        return self::firstMatch($context, $field)
            ?: self::firstMatch($extra, $field)
            ?: self::fromContext($field)
            ?: self::fromSharedContext($field)
            ?: self::fromRequest($field);
    }

    /**
     * Read the trace id from Laravel's Context facade.
     */
    public static function fromContext(?string $field = null): string
    {
        try {
            return self::firstMatchFrom(
                static fn (string $key): mixed => Context::get($key),
                $field
            );
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Read the trace id from Log::sharedContext().
     */
    public static function fromSharedContext(?string $field = null): string
    {
        try {
            return self::firstMatch(Log::sharedContext(), $field);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Read the trace id from the current request input/route parameters.
     */
    public static function fromRequest(?string $field = null): string
    {
        try {
            if (! app()->bound('request')) {
                return '';
            }

            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return '';
            }

            return self::firstMatchFrom(
                static fn (string $key): mixed => request()->input($key),
                $field
            );
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function firstMatch(array $values, ?string $field = null): string
    {
        foreach (self::candidates($field) as $key) {
            $value = self::normalize($values[$key] ?? null);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  callable(string): mixed  $resolver
     */
    private static function firstMatchFrom(callable $resolver, ?string $field = null): string
    {
        foreach (self::candidates($field) as $key) {
            $value = self::normalize($resolver($key));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function normalize(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return '';
    }

    private static function configString(string $key, string $default): string
    {
        try {
            $value = config($key, $default);
        } catch (Throwable) {
            return $default;
        }

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
