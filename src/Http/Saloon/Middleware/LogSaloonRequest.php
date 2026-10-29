<?php

namespace Overfirmament\OverUtils\Http\Saloon\Middleware;

use Overfirmament\OverUtils\Http\Saloon\SaloonLogger;
use Saloon\Contracts\RequestMiddleware;
use Saloon\Http\PendingRequest;

final class LogSaloonRequest implements RequestMiddleware
{
    public function __invoke(PendingRequest $pendingRequest): void
    {
        if (! SaloonLogger::enabled()) {
            return;
        }

        // Start time, trace resolution and downstream propagation must run even
        // when request logging itself is disabled.
        SaloonLogger::prepare($pendingRequest);

        if (! SaloonLogger::logRequestEnabled() || ! SaloonLogger::shouldLog($pendingRequest)) {
            return;
        }

        SaloonLogger::recordRequest($pendingRequest);
    }
}
