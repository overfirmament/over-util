<?php

namespace Overfirmament\OverUtils\Http\Saloon\Middleware;

use Overfirmament\OverUtils\Http\Saloon\SaloonLogger;
use Saloon\Contracts\ResponseMiddleware;
use Saloon\Http\Response;

final class LogSaloonResponse implements ResponseMiddleware
{
    public function __invoke(Response $response): void
    {
        if (! SaloonLogger::enabled() || ! SaloonLogger::logResponseEnabled()) {
            return;
        }

        if (! SaloonLogger::shouldLog($response->getPendingRequest())) {
            return;
        }

        SaloonLogger::recordResponse($response);
    }
}
