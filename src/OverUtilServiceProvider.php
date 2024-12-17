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
        $this->registerPublishing();
    }

    public function register()
    {
//        $this->commands($this->commands);
    }

    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/over_util.php' => config_path('over_util.php'),
            ], 'overutil-config');
        }
    }
}