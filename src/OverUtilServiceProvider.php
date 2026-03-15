<?php

namespace Overfirmament\OverUtils;

use Illuminate\Support\ServiceProvider;
use Overfirmament\OverUtils\Console\Tool\AutoGenerate;
use Overfirmament\OverUtils\Console\Tool\MakeRepository;
use Overfirmament\OverUtils\Console\Tool\MakeService;

class OverUtilServiceProvider extends ServiceProvider
{
    protected array $commands = [
        Console\InstallCommand::class,
        AutoGenerate::class,
        MakeService::class,
        MakeRepository::class,
    ];

    public function boot()
    {

    }

    public function register()
    {
        $this->commands($this->commands);
    }
}