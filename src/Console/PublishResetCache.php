<?php

namespace Overfirmament\OverUtils\Console;

use Illuminate\Console\Command;

class PublishResetCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publish:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '理论上每次发布代码后都要跑这个命令，用于重置各类缓存';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 重置路由缓存
        $this->call("route:clear");
        $this->call("route:cache");
        // 添加 laravel-data 缓存
        if (class_exists("Spatie\\LaravelData\\Data")) {
            $this->call("data:cache-structures");
        }
    }
}
