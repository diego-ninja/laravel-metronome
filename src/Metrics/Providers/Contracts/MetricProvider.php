<?php

namespace Ninja\Metronome\Metrics\Providers\Contracts;

interface MetricProvider
{
    public function register(): void;
}