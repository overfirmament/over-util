<?php

namespace Overfirmament\OverUtils\Tests\Fixtures;

use Overfirmament\OverUtils\Traits\ApiResponse;
use Overfirmament\OverUtils\Traits\EnumDescriptionTrait;
use Overfirmament\OverUtils\Traits\EnumHandleTrait;
use Overfirmament\OverUtils\Traits\ModelDateFormat;

class TraitConsumer
{
    use ApiResponse;
    use ModelDateFormat;
}

enum TraitConsumerEnum: string
{
    use EnumDescriptionTrait;
    use EnumHandleTrait;

    case One = 'one';

    public function description(): string
    {
        return 'One';
    }
}
