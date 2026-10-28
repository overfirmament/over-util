<?php

namespace Overfirmament\OverUtils;

use Illuminate\Support\ServiceProvider;
use Overfirmament\OverUtils\Console\InstallCommand;
use Overfirmament\OverUtils\Console\PublishResetCache;
use Overfirmament\OverUtils\Console\Tool\AutoGenerate;
use Overfirmament\OverUtils\Console\Tool\MakeRepository;
use Overfirmament\OverUtils\Console\Tool\MakeService;
use Overfirmament\OverUtils\ToolBox\RabbitMqConnectionManager;

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
        $this->mergeConfigFrom(__DIR__.'/../config/logchannel.php', 'logchannel');
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
    }

    /**
     * Make the package log channels available even when the install command was
     * not executed. The definitions live in the internal config/logchannel.php
     * file, which is never published to the application.
     */
    private function registerLoggingChannels(): void
    {
        $channels = config('logchannel.channels', []);

        if (! is_array($channels)) {
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
}
