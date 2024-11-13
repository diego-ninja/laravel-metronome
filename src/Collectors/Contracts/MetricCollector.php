<?php

namespace Ninja\Metronome\Collectors\Contracts;

interface MetricCollector
{
    public function collect(): void;
    public function listen(): void;
}
