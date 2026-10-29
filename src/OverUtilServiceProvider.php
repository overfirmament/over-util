<?php

namespace Overfirmament\OverUtils;

use Illuminate\Support\ServiceProvider;
use Overfirmament\OverUtils\Console\InstallCommand;
use Overfirmament\OverUtils\Console\PublishResetCache;
use Overfirmament\OverUtils\Console\Support\LoggingChannels;
use Overfirmament\OverUtils\Console\Tool\AutoGenerate;
use Overfirmament\OverUtils\Console\Tool\MakeRepository;
use Overfirmament\OverUtils\Console\Tool\MakeService;
use Overfirmament\OverUtils\Http\Saloon\Middleware\LogSaloonFatal;
use Overfirmament\OverUtils\Http\Saloon\Middleware\LogSaloonRequest;
use Overfirmament\OverUtils\Http\Saloon\Middleware\LogSaloonResponse;
use Overfirmament\OverUtils\Http\Saloon\SaloonLogger;
use Overfirmament\OverUtils\ToolBox\RabbitMqConnectionManager;
use Saloon\Config as SaloonConfig;
use Saloon\Enums\PipeOrder;
use Saloon\Helpers\Pipeline;
use Throwable;

class OverUtilServiceProvider extends ServiceProvider
{
    /**
     * @var array<int, class-string>
     */
    protected array $commands = [
        InstallCommand::class,
        PublishResetCache::class,
        AutoGenerate::class,
        MakeService::class,
        MakeRepository::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/overutil.php', 'overutil');
        $this->mergeConfigFrom(__DIR__.'/../config/alarm.php', 'alarm');
        $this->mergeConfigFrom(__DIR__.'/../config/rabbitmq.php', 'rabbitmq');

        $this->app->singleton(RabbitMqConnectionManager::class);

        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/overutil.php' => config_path('overutil.php'),
        ], 'over-util-config');

        $this->publishes([
            __DIR__.'/../config/alarm.php' => config_path('alarm.php'),
        ], 'over-util-alarm-config');

        $this->publishes([
            __DIR__.'/../config/rabbitmq.php' => config_path('rabbitmq.php'),
        ], 'over-util-rabbitmq-config');

        $this->registerLoggingChannels();
        $this->registerSaloonLogging();
    }

    /**
     * Make the package log channels available even when the install command was
     * not executed. The definitions live in the internal
     * src/Console/Stubs/logging-channels.stub template, which is never published.
     */
    private function registerLoggingChannels(): void
    {
        try {
            $channels = LoggingChannels::channels();
        } catch (Throwable) {
            return;
        }

        foreach ($channels as $name => $channel) {
            if (! is_array($channel) || config("logging.channels.{$name}") !== null) {
                continue;
            }

            if (isset($channel['path']) && is_string($channel['path']) && ! $this->isAbsolutePath($channel['path'])) {
                $channel['path'] = storage_path($channel['path']);
            }

            config()->set("logging.channels.{$name}", $channel);
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    /**
     * Register Saloon's global middleware so every connector is covered without
     * touching application code. Saloon keeps this pipeline in a static
     * property, so the pipes are added by name and only once.
     */
    private function registerSaloonLogging(): void
    {
        if (! SaloonLogger::enabled()) {
            return;
        }

        $pipeline = SaloonConfig::globalMiddleware();

        if (! $this->saloonPipeExists($pipeline->getRequestPipeline(), 'overutil.saloon.request')) {
            $pipeline->onRequest(new LogSaloonRequest, 'overutil.saloon.request', PipeOrder::LAST);
        }

        if (! $this->saloonPipeExists($pipeline->getResponsePipeline(), 'overutil.saloon.response')) {
            $pipeline->onResponse(new LogSaloonResponse, 'overutil.saloon.response', PipeOrder::LAST);
        }

        if (! $this->saloonPipeExists($pipeline->getFatalPipeline(), 'overutil.saloon.fatal')) {
            $pipeline->onFatalException(new LogSaloonFatal, 'overutil.saloon.fatal', PipeOrder::LAST);
        }
    }

    private function saloonPipeExists(Pipeline $pipeline, string $name): bool
    {
        foreach ($pipeline->getPipes() as $pipe) {
            if ($pipe->name === $name) {
                return true;
            }
        }

        return false;
    }
}
