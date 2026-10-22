<?php

namespace Overfirmament\OverUtils\Tests;

use Overfirmament\OverUtils\Traits\ApiResponse;
use PHPUnit\Framework\Attributes\Test;

class ApiResponseTest extends TestCase
{
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
