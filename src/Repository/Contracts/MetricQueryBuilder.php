<?php

namespace Ninja\Metronome\Repository\Contracts;

use Closure;
use Illuminate\Support\Collection;
use Ninja\Metronome\Dto\Dimension;
use Ninja\Metronome\Dto\DimensionCollection;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Repository\Dto\Metric;
use Ninja\Metronome\Repository\Dto\MetricCriteria;
use Ninja\Metronome\ValueObjects\TimeRange;

interface MetricQueryBuilder
{
    public function withCriteria(MetricCriteria $criteria): self;

    public function withDimension(Dimension $dimension): self;

    public function withDimensions(DimensionCollection $dimensions): self;

    public function withType(MetricType $type): self;

    public function withTypes(array $types): self;

    public function forAggregation(Aggregation $window): self;

    public function withTimeRange(TimeRange $timeRange): self;

    public function withName(string $name): self;

    public function orderBy(string $column, string $direction = 'asc'): self;

    public function orderByComputed(string $direction = 'asc'): self;

    public function orderByTimestamp(string $direction = 'asc'): self;

    public function limit(int $limit): self;

    public function havingComputed(string $operator, float $value): self;

    public function groupByDimension(string $dimension): self;

    public function groupByDimensions(array $dimensions): self;

    public function groupByAggregation(Aggregation $aggregation): self;

    public function whereInSubquery(string $column, Closure $callback): self;

    public function joinMetrics(
        string $metricName,
        ?string $alias = null,
        ?Closure $callback = null,
        string $joinType = 'inner'
    ): self;

    public function wherePercentile(float $percentile, string $direction = '>='): self;

    public function withCorrelatedMetrics(string $metricName, float $threshold = 0.7): self;

    public function withChangeRate(): self;

    public function aggregate(string $function, array $columns): self;

    /**
     * @return Collection<Metric>
     */
    public function get(): Collection;

    public function first(): ?Metric;

    public function count(): int;

    public function sum(): float;

    public function avg(): float;

    public function min(): float;

    public function max(): float;
}
