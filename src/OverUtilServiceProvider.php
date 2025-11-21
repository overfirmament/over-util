<?php

namespace Overfirmament\OverUtils;

use Illuminate\Support\ServiceProvider;

class OverUtilServiceProvider extends ServiceProvider
{
    protected array $commands = [
        Console\InstallCommand::class,
    ];

    public function boot()
    {

    }

    public function register()
    {
        $this->commands($this->commands);
    }
}