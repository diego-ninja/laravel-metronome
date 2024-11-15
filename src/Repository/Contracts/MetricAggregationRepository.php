<?php

namespace Ninja\Metronome\Repository\Contracts;

use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Repository\Dto\Metric;

interface MetricAggregationRepository
{
    public function store(Metric $metric): void;

    public function query(): MetricQueryBuilder;

    public function prune(Aggregation $window): int;

    public function hasMetrics(Aggregation $aggregation): bool;
}
