<?php

namespace Ninja\Metronome\Contracts;

use Ninja\Metronome\Repository\Dto\Metric;

interface ShouldReportMetric
{
    public function metric(): Metric;
}
