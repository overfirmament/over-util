<?php

namespace Overfirmament\OverUtils\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Overfirmament\OverUtils\Logger\LogFormatter;

class InstallCommand extends Command
{
    protected $signature = "overutil:install";

    protected $description = "install util and publish config";


    public function handle()
    {
        $this->writeLogFormatChannel();
    }


    public function writeLogFormatChannel(): void
    {
        $config = config("logging");
        $loggingChannelsConfig = $config["channels"] ?? [];

        if (filled($loggingChannelsConfig) && is_array($loggingChannelsConfig)) {
            if (!Arr::exists($loggingChannelsConfig, "http_in")) {
                $loggingChannelsConfig["http_in"] = [
                    'driver' => 'daily',
                    'path' => storage_path('logs/http/in_info.log'),
                    'level' => env('LOG_LEVEL', 'debug'),
                    'days' => 14,
                    'formatter' => LogFormatter::class
                ];
            }

            if (!Arr::exists($loggingChannelsConfig, "http_out")) {
                $loggingChannelsConfig["http_out"] = [
                    'driver' => 'daily',
                    'path' => storage_path('logs/http/out_info.log'),
                    'level' => env('LOG_LEVEL', 'debug'),
                    'days' => 14,
                    'formatter' => LogFormatter::class
                ];
            }

            // 将配置保存回 config/logging.php
            $loggingConfigPath = config_path('logging.php');
            $loggingConfig = file_get_contents($loggingConfigPath);

            // 使用正则表达式替换旧的 'channels' 配置（如果有的话）
            $pattern = "/'channels' => \[(.*?)\],/s";
            $replacement = "'channels' => " . var_export($loggingChannelsConfig, true) . ',';
            $newLoggingConfig = preg_replace($pattern, $replacement, $loggingConfig);

            // 保存修改后的配置文件
            file_put_contents($loggingConfigPath, $newLoggingConfig);

            $this->info("Log format channel has been added to config/logging.php");
        } else {
            $this->error("Failed to add log format channel to config/logging.php, please try again manually");
        }
    }
}