<?php

namespace Ninja\Metronome\Metrics\Handlers\Validators;

use Ninja\Metronome\Contracts\MetricValue;

final class MetricValueValidator
{
    private function __construct() {}

    public static function validate(
        MetricValue $value,
        ?float $min = null,
        ?float $max = null,
        bool $allowNegative = false
    ): bool {
        if (! $allowNegative && $value->value() < 0) {
            return false;
        }

        if ($min !== null && $value->value() < $min) {
            return false;
        }

        if ($max !== null && $value->value() > $max) {
            return false;
        }

        return ! is_infinite($value->value()) && ! is_nan($value->value());
    }
}
