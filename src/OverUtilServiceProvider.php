<?php

namespace Overfirmament\OverUtils;

use Illuminate\Support\ServiceProvider;
use Overfirmament\OverUtils\Console\InstallCommand;

class OverUtilServiceProvider extends ServiceProvider
{
    protected array $commands = [
        InstallCommand::class
    ];

    public function register(): void
    {
        $this->commands($this->commands);
    }
}