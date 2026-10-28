<?php

namespace Overfirmament\OverUtils\Console;

use Illuminate\Console\Command;
use Overfirmament\OverUtils\Console\Support\AppServiceProviderPatcher;
use Overfirmament\OverUtils\Console\Support\AppServiceProviderPatchResult;
use Overfirmament\OverUtils\Console\Support\LoggingChannels;
use Overfirmament\OverUtils\Console\Support\LoggingConfigPatcher;
use Throwable;

class InstallCommand extends Command
{
    protected $signature = 'overutil:install
        {--force : 覆盖已发布的配置文件和已存在的日志 channel}
        {--dry-run : 只显示将要修改的内容，不写入文件}';

    protected $description = 'Install over-util resources and configure logging channels';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun) {
            $this->publishConfiguration($force);
        } else {
            $this->components->info('Dry run：跳过配置文件发布。');
        }

        $loggingPath = config_path('logging.php');

        if (! is_file($loggingPath) && ! $dryRun) {
            $this->components->info('未找到 config/logging.php，正在发布 Laravel 默认日志配置...');
            $this->call('config:publish', ['name' => 'logging']);
        }

        if (! is_file($loggingPath)) {
            $this->components->error("未找到 {$loggingPath}，请先执行 php artisan config:publish logging。");

            return self::FAILURE;
        }

        try {
            $result = (new LoggingConfigPatcher)->patch(
                $loggingPath,
                LoggingChannels::snippets(),
                $force,
                $dryRun
            );
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $result->hasChanges()) {
            $this->components->info('日志 channel 已是最新，无需修改。');
        } else {
            $this->components->info($dryRun ? '将要修改 config/logging.php：' : '已更新 config/logging.php：');

            foreach ($result->added as $name) {
                $this->line("  <fg=green>+</> {$name}");
            }

            foreach ($result->replaced as $name) {
                $this->line("  <fg=yellow>~</> {$name}");
            }

            foreach ($result->skipped as $name) {
                $this->line("  <fg=gray>=</> {$name}（已存在，已跳过）");
            }

            foreach ($result->warnings as $warning) {
                $this->components->warn($warning);
            }

            if ($result->backupPath !== null) {
                $this->line("  备份文件：{$result->backupPath}");
            }
        }

        $providerResult = $this->patchAppServiceProvider($dryRun);
        $changed = $result->hasChanges() || ($providerResult?->hasChanges() ?? false);

        if (! $dryRun && $changed) {
            $this->components->info('正在清理配置缓存...');
            $this->call('config:clear');
        }

        return self::SUCCESS;
    }

    private function patchAppServiceProvider(bool $dryRun): ?AppServiceProviderPatchResult
    {
        $path = app_path('Providers/AppServiceProvider.php');

        if (! is_file($path)) {
            $this->components->warn("未找到 {$path}，跳过 WorkerStopping 示例注入。");

            return null;
        }

        try {
            $result = (new AppServiceProviderPatcher)->patch($path, $dryRun);
        } catch (Throwable $e) {
            $this->components->warn($e->getMessage());

            return null;
        }

        if (! $result->hasChanges()) {
            $this->components->info('AppServiceProvider 已包含 WorkerStopping 示例，已跳过。');

            return $result;
        }

        $this->components->info(
            $dryRun
                ? '将向 AppServiceProvider.php 注入 WorkerStopping 示例（默认注释）。'
                : '已向 AppServiceProvider.php 注入 WorkerStopping 示例（默认注释）。'
        );

        if ($result->backupPath !== null) {
            $this->line("  备份文件：{$result->backupPath}");
        }

        return $result;
    }

    private function publishConfiguration(bool $force): void
    {
        $sourceDirectory = dirname(__DIR__, 2).'/config';

        // logchannel.php is package-internal and deliberately left out: its
        // channels are written into the application's config/logging.php.
        foreach (['overutil.php', 'alarm.php', 'rabbitmq.php'] as $file) {
            $source = $sourceDirectory.'/'.$file;
            $target = config_path($file);

            if (! is_file($source)) {
                $this->components->warn("配置模板不存在：{$source}，已跳过 config/{$file}。");

                continue;
            }

            if (is_file($target) && ! $force) {
                $this->components->task("跳过 config/{$file}（已存在）", fn () => true);

                continue;
            }

            $directory = dirname($target);

            if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
                $this->components->warn("无法创建配置目录：{$directory}，已跳过 config/{$file}。");

                continue;
            }

            if (! @copy($source, $target)) {
                $this->components->warn("发布 config/{$file} 失败，已跳过。");

                continue;
            }

            $this->components->task("发布 config/{$file}", fn () => true);
        }
    }
}
