<?php

namespace Overfirmament\OverUtils\Support;

use DateInterval;
use DateTimeInterface;
use Laravel\Passport\Passport;
use Throwable;

/**
 * Resolves the number of seconds until an access token expires.
 *
 * Explicit values always win. When no value is given the resolver tries the
 * current guard first (tymon/jwt-auth exposes factory()->getTTL()), then falls
 * back to framework/package configuration.
 */
final class TokenExpiration
{
    /**
     * @return int|null seconds, or null when it cannot be determined
     */
    public static function resolve(
        int|DateTimeInterface|DateInterval|null $expiresIn = null,
        ?string $guard = null
    ): ?int {
        if (is_int($expiresIn)) {
            return max(0, $expiresIn);
        }

        if ($expiresIn instanceof DateTimeInterface) {
            return max(0, $expiresIn->getTimestamp() - now()->getTimestamp());
        }

        if ($expiresIn instanceof DateInterval) {
            $base = now();

            return max(0, $base->copy()->add($expiresIn)->getTimestamp() - $base->getTimestamp());
        }

        return self::fromGuard($guard);
    }

    private static function fromGuard(?string $guardName): ?int
    {
        try {
            $guard = auth()->guard($guardName);
        } catch (Throwable) {
            return self::fromConfig($guardName);
        }

        // tymon/jwt-auth: JWTGuard::factory()->getTTL(), unit: minutes.
        if (method_exists($guard, 'factory')) {
            try {
                $factory = $guard->factory();

                if (is_object($factory) && method_exists($factory, 'getTTL')) {
                    return self::minutesToSeconds($factory->getTTL());
                }
            } catch (Throwable) {
                // Fall through to configuration based fallbacks.
            }
        }

        return self::fromConfig($guardName);
    }

    private static function fromConfig(?string $guardName): ?int
    {
        $guardName ??= config('auth.defaults.guard');
        $driver = config("auth.guards.{$guardName}.driver");

        // tymon/jwt-auth, unit: minutes.
        if ($driver === 'jwt' && config('jwt.ttl') !== null) {
            return self::minutesToSeconds(config('jwt.ttl'));
        }

        // Laravel Sanctum, unit: minutes. A null value means "never expires".
        if ($driver === 'sanctum' && config('sanctum.expiration') !== null) {
            return self::minutesToSeconds(config('sanctum.expiration'));
        }

        // Laravel Passport.
        if (class_exists(Passport::class)) {
            try {
                $expiresIn = Passport::tokensExpireIn();

                if ($expiresIn instanceof DateTimeInterface) {
                    return max(0, $expiresIn->getTimestamp() - now()->getTimestamp());
                }

                if ($expiresIn instanceof DateInterval) {
                    $base = now();

                    return max(0, $base->copy()->add($expiresIn)->getTimestamp() - $base->getTimestamp());
                }
            } catch (Throwable) {
                // Ignore optional package failures.
            }
        }

        return null;
    }

    private static function minutesToSeconds(mixed $minutes): ?int
    {
        if (! is_numeric($minutes)) {
            return null;
        }

        return max(0, (int) $minutes * 60);
    }
}
