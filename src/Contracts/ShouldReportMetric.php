<?php

namespace Ninja\Metronome\Contracts;

use Ninja\Metronome\Repository\Builder\Dto\Metric;

interface ShouldReportMetric
{
    public function metric(): Metric;
}
