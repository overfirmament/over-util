<?php

namespace Overfirmament\OverUtils;

use Illuminate\Support\ServiceProvider;

class OverUtilServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerPublishing();
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/logging.php', 'channels'
        );
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