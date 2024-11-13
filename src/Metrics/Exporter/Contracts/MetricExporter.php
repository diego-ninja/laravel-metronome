<?php

namespace Ninja\Metronome\Metrics\Exporter\Contracts;

interface MetricExporter
{
    public function export(): string;
}
