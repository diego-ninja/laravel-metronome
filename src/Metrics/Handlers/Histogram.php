<?php

namespace Ninja\Metronome\Metrics\Handlers;

use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Dto\Value\HistogramMetricValue;

final class Histogram extends AbstractMetricHandler
{
    public function __construct(private readonly array $buckets)
    {
    }

    public function compute(array $values): MetricValue
    {
        $this->validateOrFail($values);

        $count = count($values);
        $sum = array_sum(array_column($values, 'value'));
        $mean = $count > 0 ? $sum / $count : 0;

        return new HistogramMetricValue(
            value: $mean,
            buckets: $this->buckets,
            count: $count,
            sum: $sum
        );
    }
}
