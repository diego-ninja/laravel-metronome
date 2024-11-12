<?php

namespace Ninja\Metronome\Exceptions;

use Exception;
use Ninja\Metronome\Enums\MetricType;

class MetricHandlerNotFoundException extends Exception
{
    public static function forType(MetricType $type): self
    {
        return new self(
            sprintf(
                'Metric handler for type %s not found',
                $type->value
            )
        );
    }
}
