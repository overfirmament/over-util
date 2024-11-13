<?php

namespace Overfirmament\OverUtils\Traits;

trait ModelDateFormat
{
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
