<?php

namespace Overfirmament\OverUtils\Console\Tool;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AutoGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:autogen {modelName} {--S|service} {--R|repository}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto generate model or service and repository';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $model = $this->argument("modelName");
        $options = $this->options();

        $this->call("make:model", [
            "name" => $model
        ]);


        if ($options["repository"]) {
            $this->call("make:repository", [
                "name" => $model . "Repository"
            ]);
        }
        if ($options["service"]) {
            $service = Str::afterLast($model, "/");
            $this->call("make:service", [
                "name" => $service . "Service"
            ]);
        }
    }
}
