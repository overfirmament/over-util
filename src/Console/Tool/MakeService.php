<?php

namespace Overfirmament\OverUtils\Console\Tool;

use Illuminate\Console\GeneratorCommand;

class MakeService extends GeneratorCommand
{
    protected $name = 'make:service';

    protected $description = 'Create a new service class';

    protected $type = 'Service';

    #[\Override]
    protected function getStub(): string
    {
        return dirname(__DIR__).'/Stubs/class.stub';
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Services';
    }
}
