<?php

namespace Ninja\Metronome\Contracts;

use Ninja\Metronome\Exceptions\InvalidMetricException;

interface MetricHandler
{
    /**
     * @param array<array{value: float, timestamp: int, metadata?: array}> $values
     * @throws InvalidMetricException
     */
    public function compute(array $values): MetricValue;

    /**
     * @param array<array{value: float, timestamp: int, metadata?: array}> $values
     */
    public function validate(array $values): bool;
}
