<?php

namespace Ninja\Metronome\Metrics\Exporter\Contracts;

interface Exportable
{
    public function export(): array;
}