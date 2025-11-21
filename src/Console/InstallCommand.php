<?php

namespace Overfirmament\OverUtils\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'overutil:install';
    protected $description = 'Install overutils resources';

    public function handle()
    {
        $this->call("vendor:publish", ["--tag" => "saloon-config"]);
        $this->call("vendor:publish", [
            "--provider" => "Spatie\LaravelData\LaravelDataServiceProvider",
            "--tag" => "data-config"
        ]);
    }
}