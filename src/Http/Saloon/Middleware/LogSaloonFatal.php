<?php

namespace Overfirmament\OverUtils\Http\Saloon\Middleware;

use Overfirmament\OverUtils\Http\Saloon\SaloonLogger;
use Saloon\Exceptions\Request\FatalRequestException;

final class LogSaloonFatal
{
    public function __invoke(FatalRequestException $exception): void
    {
        if (! SaloonLogger::enabled() || ! SaloonLogger::logFatalEnabled()) {
            return;
        }

        if (! SaloonLogger::shouldLog($exception->getPendingRequest())) {
            return;
        }

        SaloonLogger::recordFatal($exception);
    }
}
