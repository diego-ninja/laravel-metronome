<?php

namespace Ninja\Metronome\Metrics\Exporter\Metric;

use Ninja\Metronome\Dto\Dimension;
use Ninja\Metronome\Metrics\Exporter\Contracts\Exportable;
use Ninja\Metronome\Metrics\Registry;
use Ninja\Metronome\Repository\Dto\Metric;

final readonly class GaugeExporter extends AbstractMetricExporter implements Exportable
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
