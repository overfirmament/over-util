<?php

namespace Overfirmament\OverUtils\Console;

use Illuminate\Console\Command;
use Spatie\LaravelData\Data;

class PublishResetCache extends Command
{
    /**
     * @var string
     */
    protected $signature = 'overutil:cache-reset';

    /**
     * @var array<int, string>
     */
    protected $aliases = ['publish:cache'];

    /**
     * @var string
     */
    protected $description = '理论上每次发布代码后都要跑这个命令，用于重置各类缓存';

    public function handle(): int
    {
        $this->call('route:clear');
        $this->call('route:cache');

        if (class_exists(Data::class)) {
            $this->call('data:cache-structures');
        }

        return self::SUCCESS;
    }
}
