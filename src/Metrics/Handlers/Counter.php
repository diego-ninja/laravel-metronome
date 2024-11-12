<?php

namespace Ninja\Metronome\Metrics\Handlers;

use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Dto\Value\CounterMetricValue;

final class Counter extends AbstractMetricHandler
{
    public function compute(array $values): MetricValue
    {
        $this->validateOrFail($values);

        return new CounterMetricValue(
            array_sum(array_column($values, 'value'))
        );
    }
}
