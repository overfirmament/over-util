<?php

namespace Overfirmament\OverUtils\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Overfirmament\OverUtils\Logger\LogFormatter;
use Symfony\Component\VarExporter\VarExporter;

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
        $httpLogchannels = require config_path("logging_channel.php");

        $configPath = config_path("logging.php");
        $config = require $configPath;
        $loggingChannelsConfig = $config["channels"] ?? [];

        if (is_array($loggingChannelsConfig)) {
            if (!Arr::exists($loggingChannelsConfig, "http_in")) {
                $loggingChannelsConfig["http_in"] = $httpLogchannels["http_in"];
            }

            if (!Arr::exists($loggingChannelsConfig, "http_out")) {
                $loggingChannelsConfig["http_out"] = $httpLogchannels["http_out"];
            }

            // 将配置保存回 config/logging.php
            $config["channels"] = $loggingChannelsConfig;

            // 使用正则表达式替换旧的 'channels' 配置（如果有的话）
            $configContent = "<?php\nreturn " . VarExporter::export($config) . ";\n";
            // 保存修改后的配置文件
            file_put_contents($configPath, $configContent);

            $this->info("Log format channel has been added to config/logging.php");
        } else {
            $this->error("Failed to add log format channel to config/logging.php, please try again manually");
        }
    }
}