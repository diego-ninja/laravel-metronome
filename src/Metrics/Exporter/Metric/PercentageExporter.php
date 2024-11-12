<?php

namespace Ninja\Metronome\Metrics\Exporter\Metric;

use Ninja\Metronome\Metrics\Exporter\Contracts\Exportable;
use Ninja\Metronome\Metrics\Registry;

final readonly class PercentageExporter extends AbstractMetricExporter implements Exportable
{
    public function export(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'help' => Registry::get($this->name)->description(),
            'value' => $this->value(),
            'labels' => $this->labels()
        ];
    }
}