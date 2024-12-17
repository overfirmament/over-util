<?php

namespace Overfirmament\OverUtils\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Overfirmament\OverUtils\Logger\LogFormatter;
use Symfony\Component\VarExporter\VarExporter;

class InstallCommand extends Command
{
    protected $signature = "overutil:install";

    protected $description = "install util and publish config";


    public function handle()
    {
        $this->writeLogFormatChannel();
    }


    public function writeLogFormatChannel(): void
    {
        $listenerPath = app_path("Listeners");
        if (!is_dir($listenerPath)) {
            mkdir($listenerPath);
            $this->info("Listeners directory has been created");
        }

        $httpRequestTo = $listenerPath . "/LogHttpOutRequest.php";
        $httpResponseTo = $listenerPath . "/LogHttpOutResponse.php";
        $httpOutRequestListener = __DIR__ . "/../Listeners/LogHttpOutRequest.php";
        $httpOutResponseListener = __DIR__ . "/../Listeners/LogHttpOutResponse.php";

        if (!file_exists($httpRequestTo)) {
            copy($httpOutRequestListener, $httpRequestTo);

            $this->info("Listener $httpRequestTo has been created");
        } else {
            $this->warn("Listener $httpRequestTo already exists");
        }

        if (!file_exists($httpResponseTo)) {
            copy($httpOutResponseListener, $httpResponseTo);

            $this->info("Listener $httpResponseTo has been created");
        } else {
            $this->warn("Listener $httpResponseTo already exists");
        }
    }
}