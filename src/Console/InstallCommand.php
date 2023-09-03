<?php

namespace Overfirmament\OverUtils\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class InstallCommand extends Command
{
    protected $signature = "overutil:install";

    protected $description = "install util and publish config";


    public function handle()
    {

    }


    public function setHttpLogConfig(): void
    {
        $loggingChannelsConfig = config("logging.channels");

        if (filled($loggingChannelsConfig) && is_array($loggingChannelsConfig)) {
            if (!Arr::exists($loggingChannelsConfig, "http_in")) {
                $loggingChannelsConfig["http_in"] = [
                    'driver' => 'daily',
                    'path' => storage_path('logs/http_in/info.log'),
                    'level' => env('LOG_LEVEL', 'debug'),
                    'days' => 14,
                    'formatter' => \App\Log\IkaLogFormatter::class
                ];
            }

            if (!Arr::exists($loggingChannelsConfig, "http_out")) {
                $loggingChannelsConfig["http_out"] = [
                    'driver' => 'daily',
                    'path' => storage_path('logs/http_in/info.log'),
                    'level' => env('LOG_LEVEL', 'debug'),
                    'days' => 14,
                    'formatter' => \App\Log\IkaLogFormatter::class
                ];
            }
        }

        config(["logging.channels" => $loggingChannelsConfig]);
    }
}