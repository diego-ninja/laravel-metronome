<?php

namespace Ninja\Metronome\Metrics\Handlers;

use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Dto\Value\GaugeMetricValue;

final class Gauge extends AbstractMetricHandler
{
    public function compute(array $values): MetricValue
    {
        $this->validateOrFail($values);

        if (empty($values)) {
            return GaugeMetricValue::empty();
        }

        usort($values, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        $latest = reset($values);

        return new GaugeMetricValue(
            (float) $latest['value'],
            (int) $latest['timestamp']
        );
    }
}
