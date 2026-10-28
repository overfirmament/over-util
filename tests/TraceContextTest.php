<?php

namespace Overfirmament\OverUtils\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Overfirmament\OverUtils\Support\TraceContext;
use PHPUnit\Framework\Attributes\Test;

class TraceContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Context::flush();
        Log::flushSharedContext();
    }

    #[Test]
    public function it_uses_trace_id_by_default(): void
    {
        $this->assertSame('trace_id', TraceContext::field());
        $this->assertSame('trace_id', TraceContext::output());
        $this->assertSame('trace-1', TraceContext::resolve(['trace_id' => 'trace-1']));
    }

    #[Test]
    public function it_reads_the_configured_field_and_its_variants(): void
    {
        config()->set('overutil.trace.field', 'requestId');

        $this->assertSame('camel', TraceContext::resolve(['requestId' => 'camel']));
        $this->assertSame('snake', TraceContext::resolve(['request_id' => 'snake']));
    }

    #[Test]
    public function it_keeps_reading_the_legacy_trace_id_when_a_custom_field_is_configured(): void
    {
        config()->set('overutil.trace.field', 'request_id');

        $this->assertSame('legacy', TraceContext::resolve(['trace_id' => 'legacy']));
        $this->assertSame('camel-legacy', TraceContext::resolve(['traceId' => 'camel-legacy']));
    }

    #[Test]
    public function explicit_log_context_wins_over_shared_context(): void
    {
        config()->set('overutil.trace.field', 'request_id');
        Context::add('request_id', 'from-context');

        $this->assertSame('from-record', TraceContext::resolve(['request_id' => 'from-record']));
        $this->assertSame('from-extra', TraceContext::resolve([], ['request_id' => 'from-extra']));
        $this->assertSame('from-context', TraceContext::resolve());
    }

    #[Test]
    public function it_reads_laravel_context_and_shared_log_context(): void
    {
        config()->set('overutil.trace.field', 'request_id');

        Context::add('request_id', 'from-context');

        $this->assertSame('from-context', TraceContext::resolve());

        Context::flush();
        Log::shareContext(['request_id' => 'from-shared']);

        $this->assertSame('from-shared', TraceContext::resolve());
    }

    #[Test]
    public function it_falls_back_to_the_current_request(): void
    {
        config()->set('overutil.trace.field', 'request_id');

        $request = Request::create('/orders', 'GET');
        $request->merge(['request_id' => 'from-request']);
        $this->app->instance('request', $request);

        $this->assertSame('from-request', TraceContext::resolve());
        $this->assertSame('from-request', TraceContext::fromRequest());
    }

    #[Test]
    public function it_ignores_non_scalar_values(): void
    {
        $this->assertSame('', TraceContext::resolve([
            'trace_id' => ['nested' => 'value'],
            'traceId' => new \stdClass,
        ]));
    }

    #[Test]
    public function it_accepts_an_explicit_field_override(): void
    {
        $this->assertSame('custom', TraceContext::resolve(['custom_field' => 'custom'], [], 'customField'));
        $this->assertSame('custom', TraceContext::resolve(['customField' => 'custom'], [], 'custom_field'));
    }
}
