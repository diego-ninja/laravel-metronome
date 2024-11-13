<?php

namespace Ninja\Metronome\Metrics\Exporter\Metric;

use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Metrics\Registry;

final readonly class RateExporter extends AbstractMetricExporter
{
    public function export(): array
    {
        $definition = Registry::get($this->name);

        return [
            'name' => sprintf('%s_per_%s', $this->name, $definition->unit()),
            'type' => MetricType::Gauge->value,
            'help' => $this->help(),
            'value' => $this->value(),
            'labels' => $this->labels()
        ];
    }
}
