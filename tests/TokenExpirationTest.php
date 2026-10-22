<?php

namespace Overfirmament\OverUtils\Tests;

use DateInterval;
use Illuminate\Support\Facades\Auth;
use Overfirmament\OverUtils\Support\TokenExpiration;
use PHPUnit\Framework\Attributes\Test;

class TokenExpirationTest extends TestCase
{
    #[Test]
    public function it_accepts_explicit_seconds(): void
    {
        $this->assertSame(3600, TokenExpiration::resolve(3600));
        $this->assertSame(0, TokenExpiration::resolve(-10));
    }

    #[Test]
    public function it_accepts_a_date_time_instance(): void
    {
        $seconds = TokenExpiration::resolve(now()->addMinutes(10));

        $this->assertGreaterThanOrEqual(599, $seconds);
        $this->assertLessThanOrEqual(600, $seconds);
    }

    #[Test]
    public function it_accepts_a_date_interval(): void
    {
        $seconds = TokenExpiration::resolve(new DateInterval('PT10M'));

        $this->assertGreaterThanOrEqual(599, $seconds);
        $this->assertLessThanOrEqual(600, $seconds);
    }

    #[Test]
    public function it_uses_the_jwt_guard_factory_ttl_in_minutes(): void
    {
        Auth::extend('fake-jwt', static function () {
            return new class
            {
                public function factory(): object
                {
                    return new class
                    {
                        public function getTTL(): int
                        {
                            return 15;
                        }
                    };
                }
            };
        });

        config([
            'auth.defaults.guard' => 'fake',
            'auth.guards.fake' => ['driver' => 'fake-jwt', 'provider' => 'users'],
        ]);

        $this->assertSame(900, TokenExpiration::resolve(null, 'fake'));
    }

    #[Test]
    public function it_falls_back_to_jwt_config_when_the_guard_cannot_be_resolved(): void
    {
        config([
            'auth.defaults.guard' => 'api',
            'auth.guards.api' => ['driver' => 'jwt', 'provider' => 'users'],
            'jwt.ttl' => 60,
        ]);

        $this->assertSame(3600, TokenExpiration::resolve(null, 'api'));
    }

    #[Test]
    public function it_falls_back_to_sanctum_config(): void
    {
        config([
            'auth.defaults.guard' => 'sanctum',
            'auth.guards.sanctum' => ['driver' => 'sanctum', 'provider' => 'users'],
            'sanctum.expiration' => 30,
        ]);

        $this->assertSame(1800, TokenExpiration::resolve(null, 'sanctum'));
    }

    #[Test]
    public function it_returns_null_when_nothing_can_be_resolved(): void
    {
        config([
            'auth.defaults.guard' => 'web',
            'auth.guards.web' => ['driver' => 'session', 'provider' => 'users'],
        ]);

        $this->assertNull(TokenExpiration::resolve(null, 'web'));
    }
}
