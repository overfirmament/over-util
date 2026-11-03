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
    public function business_codes_never_become_the_http_status(): void
    {
        foreach ([1001, 400408, 400, 500] as $code) {
            $response = (new ApiResponder)->failed('business error', $code);

            $this->assertSame(200, $response->getStatusCode(), "Business code {$code} leaked into the HTTP status.");
            $this->assertSame($code, $response->getData(true)['code']);
        }
    }

    #[Test]
    public function status_does_not_derive_the_http_status_from_the_business_code(): void
    {
        $response = (new ApiResponder)->status('error', [], 400408);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(400408, $response->getData(true)['code']);
    }

    #[Test]
    public function failed_accepts_an_explicit_http_status(): void
    {
        $response = (new ApiResponder)->failed('business error', 400408, 'error', 400);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(400408, $response->getData(true)['code']);
    }

    #[Test]
    public function set_http_code_applies_to_failed_responses(): void
    {
        $response = (new ApiResponder)->setHttpCode(503)->failed('down', 400408);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(400408, $response->getData(true)['code']);
    }

    #[Test]
    public function respond_and_jsonp_accept_an_explicit_http_status(): void
    {
        $respond = (new ApiResponder)->respond(['ok' => true], httpCode: 202);
        $jsonp = (new ApiResponder)->jsonp(['ok' => true], 'callback', 'success', 202);

        $this->assertSame(202, $respond->getStatusCode());
        $this->assertSame(202, $jsonp->getStatusCode());
    }

    #[Test]
    public function semantic_helpers_keep_their_explicit_http_status(): void
    {
        $this->assertSame(422, (new ApiResponder)->error('invalid')->getStatusCode());
        $this->assertSame(500, (new ApiResponder)->internalError()->getStatusCode());
        $this->assertSame(404, (new ApiResponder)->notFound()->getStatusCode());
        $this->assertSame(422, (new ApiResponder)->error('invalid', 400408)->getStatusCode());
    }

    #[Test]
    public function shortcut_methods_do_not_leak_the_http_status_to_later_responses(): void
    {
        $responder = new ApiResponder;

        $created = $responder->created(['id' => 1]);
        $success = $responder->success(['id' => 2]);
        $failed = $responder->failed('error', 400408, 'error', 400);
        $again = $responder->success(['id' => 3]);
        $deleted = $responder->deleted();
        $afterDelete = $responder->success(['id' => 4]);

        $this->assertSame(201, $created->getStatusCode());
        $this->assertSame(200, $success->getStatusCode());
        $this->assertSame(400, $failed->getStatusCode());
        $this->assertSame(200, $again->getStatusCode());
        $this->assertSame(204, $deleted->getStatusCode());
        $this->assertSame(200, $afterDelete->getStatusCode());
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
