<?php

namespace Overfirmament\OverUtils\Tests;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Overfirmament\OverUtils\Traits\ApiResponse;
use PHPUnit\Framework\Attributes\Test;

class ApiResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Context::flush();
        Log::flushSharedContext();
    }

    #[Test]
    public function it_adds_the_trace_id_to_responses(): void
    {
        Context::add('trace_id', 'trace-1');

        $data = (new ApiResponder)->success(['id' => 1])->getData(true);

        $this->assertSame('trace-1', $data['trace_id']);
    }

    #[Test]
    public function it_uses_the_configured_output_field(): void
    {
        config()->set('overutil.trace.output', 'request_id');
        Context::add('trace_id', 'trace-2');

        $data = (new ApiResponder)->success(['id' => 1])->getData(true);

        $this->assertSame('trace-2', $data['request_id']);
        $this->assertArrayNotHasKey('trace_id', $data);
    }

    #[Test]
    public function it_resolves_a_custom_field_from_context(): void
    {
        config()->set('overutil.trace.field', 'request_id');
        Context::add('request_id', 'req-1');

        $data = (new ApiResponder)->success(['id' => 1])->getData(true);

        $this->assertSame('req-1', $data['trace_id']);
    }

    #[Test]
    public function it_uses_the_http_status_code_for_success_responses(): void
    {
        $response = (new ApiResponder)->success(['id' => 1]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['id' => 1], $response->getData(true)['data']);
    }

    #[Test]
    public function created_responses_use_http_201(): void
    {
        $response = (new ApiResponder)->created(['id' => 1]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('created', $response->getData(true)['status']);
    }

    #[Test]
    public function deleted_responses_use_http_204(): void
    {
        $response = (new ApiResponder)->deleted();

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    #[Test]
    public function not_found_responses_use_http_404(): void
    {
        $response = (new ApiResponder)->notFound('missing');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('missing', $response->getData(true)['message']);
    }

    #[Test]
    public function non_http_business_codes_do_not_become_the_http_status(): void
    {
        $response = (new ApiResponder)->failed('business error', 1001);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(1001, $response->getData(true)['code']);
    }

    #[Test]
    public function token_responses_resolve_expiration_in_seconds(): void
    {
        $response = (new ApiResponder)->respondWithToken('token', 'Bearer', 3600);
        $data = $response->getData(true);

        $this->assertSame('token', $data['data']['access_token']);
        $this->assertSame(3600, $data['data']['expires_in']);
    }
}

class ApiResponder
{
    use ApiResponse;
}
