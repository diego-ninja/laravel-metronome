<?php

namespace Ninja\Metronome\Repository\Builder;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Ninja\Metronome\Dto\Dimension;
use Ninja\Metronome\Dto\DimensionCollection;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Repository\Contracts\MetricQueryBuilder as QueryBuilder;
use Ninja\Metronome\Repository\Dto\Metric;
use Ninja\Metronome\Repository\Dto\MetricCriteria;
use Ninja\Metronome\ValueObjects\TimeRange;

class MetricQueryBuilder implements QueryBuilder
{
    private const METRIC_TABLE = 'metronome_metrics';

    public const VALID_TIME_WINDOWS = [
        Aggregation::Realtime->value => "DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:00')",
        Aggregation::Hourly->value => "DATE_FORMAT(timestamp, '%Y-%m-%d %H:00:00')",
        Aggregation::Daily->value => 'DATE(timestamp)',
        Aggregation::Weekly->value => 'DATE(DATE_SUB(timestamp, INTERVAL WEEKDAY(timestamp) DAY))',
        Aggregation::Monthly->value => "DATE_FORMAT(timestamp, '%Y-%m-01')",
        Aggregation::Yearly->value => "DATE_FORMAT(timestamp, '%Y-01-01')",
    ];

    protected Builder $query;

    public function __construct(Builder $baseQuery)
    {
        $this->query = $baseQuery;
    }

    public function withCriteria(MetricCriteria $criteria): self
    {
        if ($criteria->names) {
            $this->query->whereIn('name', $criteria->names);
        }

        if ($criteria->types) {
            $this->query->whereIn('type', array_map(fn ($type) => $type->value, $criteria->types));
        }

        if ($criteria->aggregations) {
            $this->query->whereIn('window', array_map(fn ($aggregation) => $aggregation->value, $criteria->aggregations));
        }

        if ($criteria->timeRange) {
            $this->query->whereBetween('timestamp', [$criteria->timeRange->from, $criteria->timeRange->to]);
        }

        if ($criteria->dimensions) {
            foreach ($criteria->dimensions as $dimension) {
                $this->query->where('dimensions', 'like', "%{$dimension->name}:{$dimension->value}%");
            }
        }

        return $this;
    }

    public function withDimension(Dimension $dimension): self
    {
        $this->query->where('dimensions', 'like', sprintf('%%"%s":"%s"%%', $dimension->name, $dimension->value));

        return $this;
    }

    public function withDimensions(DimensionCollection $dimensions): self
    {
        foreach ($dimensions as $dimension) {
            /** @var Dimension $dimension */
            $this->withDimension($dimension);
        }

        return $this;
    }

    public function withType(MetricType $type): self
    {
        $this->query->where('type', $type->value);

        return $this;
    }

    public function withTypes(array $types): self
    {
        $this->query->whereIn('type', array_map(fn ($type) => $type->value, $types));

        return $this;
    }

    public function forAggregation(Aggregation $window): self
    {
        $this->query->where('window', $window->value);

        return $this;
    }

    public function withTimeRange(TimeRange $timeRange): self
    {
        $this->query->whereBetween('timestamp', [$timeRange->from, $timeRange->to]);

        return $this;
    }

    public function withName(string $name): self
    {
        $this->query->where('name', $name);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    public function orderByComputed(string $direction = 'asc'): self
    {
        return $this->orderBy('computed', $direction);
    }

    public function orderByTimestamp(string $direction = 'asc'): self
    {
        return $this->orderBy('timestamp', $direction);
    }

    public function limit(int $limit): self
    {
        $this->query->limit($limit);

        return $this;
    }

    public function havingComputed(string $operator, float $value): self
    {
        $this->query->having('computed', $operator, $value);

        return $this;
    }

    public function groupByDimension(string $dimension): self
    {
        $expr = "JSON_UNQUOTE(JSON_EXTRACT(dimensions, '$.{$dimension}'))";
        $this->query->groupBy($this->query->raw($expr));

        return $this;
    }

    public function groupByDimensions(array $dimensions): self
    {
        foreach ($dimensions as $dimension) {
            $this->groupByDimension($dimension);
        }

        return $this;
    }

    public function groupByAggregation(Aggregation $aggregation): self
    {
        $this->query->groupByRaw(self::VALID_TIME_WINDOWS[$aggregation->value]);

        return $this;
    }

    public function whereInSubquery(string $column, Closure $callback): self
    {
        $subquery = new self($this->query->newQuery());
        $callback($subquery);
        $this->query->whereIn($column, $subquery->getQuery());

        return $this;
    }

    public function joinMetrics(string $metricName, ?string $alias = null, ?Closure $callback = null, string $joinType = 'inner'): self
    {
        $alias = $alias ?? 'm'.($this->query->joins ? count($this->query->joins) + 1 : 2);
        $joinMethod = $joinType.'Join';

        $this->query->$joinMethod(self::METRIC_TABLE.' as '.$alias, function ($join) use ($metricName, $alias, $callback) {
            $join->on('m1.timestamp', '=', $alias.'.timestamp')
                ->where($alias.'.name', '=', $metricName);
            if ($callback) {
                $callback($join);
            }
        });

        return $this;
    }

    public function joinRelatedMetrics(array $metricNames): self
    {
        foreach ($metricNames as $index => $metricName) {
            $alias = 'm'.($index + 2);
            $this->joinMetrics($metricName, $alias);
        }

        return $this;
    }

    public function wherePercentile(float $percentile, string $direction = '>='): self
    {
        $count = $this->query->count();
        $offset = floor($count * ($percentile / 100));

        $subquery = $this->query->newQuery()
            ->select('computed')
            ->orderBy('computed')
            ->limit(1)
            ->offset($offset);

        $this->query->where('computed', $direction, $subquery);

        return $this;
    }

    public function withCorrelatedMetrics(string $metricName, float $threshold = 0.7): self
    {
        $this->joinMetrics($metricName, 'm2');
        $this->query->select([
            'm1.computed as value1',
            'm2.computed as value2',
            $this->query->raw('CORR(m1.computed, m2.computed) as correlation'),
        ])
            ->having('correlation', '>=', $threshold)
            ->groupBy(['m1.name', 'm2.name']);

        return $this;
    }

    public function withChangeRate(): self
    {
        $this->query->addSelect(
            $this->query->raw('((computed - LAG(computed) OVER (ORDER BY timestamp)) / LAG(computed) OVER (ORDER BY timestamp)) * 100 as change_rate')
        );

        return $this;
    }

    public function aggregate(string $function, array $columns): self
    {
        foreach ($columns as $column) {
            $this->query->addSelect(
                $this->query->raw("$function($column) as {$function}_$column")
            );
        }

        return $this;
    }

    public function get(): Collection
    {
        return $this->query->get()->map(fn ($row) => Metric::from($row));
    }

    public function first(): ?Metric
    {
        $result = $this->query->first();

        return $result ? Metric::from($result) : null;
    }

    public function count(): int
    {
        return $this->query->count();
    }

    public function sum(): float
    {
        return (float) $this->query->sum('computed');
    }

    public function avg(): float
    {
        return (float) $this->query->avg('computed');
    }

    public function min(): float
    {
        return (float) $this->query->min('computed');
    }

    public function max(): float
    {
        return (float) $this->query->max('computed');
    }

    public function stats(): array
    {
        return [
            'count' => $this->count(),
            'avg' => $this->avg(),
            'min' => $this->min(),
            'max' => $this->max(),
            'stddev' => (float) $this->query->selectRaw('STDDEV(computed) as stddev')->value('stddev'),
            'percentiles' => $this->calculatePercentiles(),
            'histogram' => $this->calculateHistogram(),
        ];
    }

    private function calculatePercentiles(): array
    {
        $percentiles = [25, 50, 75, 90, 95, 99];
        $results = [];

        foreach ($percentiles as $p) {
            $results["p$p"] = (float) $this->query->selectRaw(
                "PERCENTILE_CONT($p/100) WITHIN GROUP (ORDER BY computed) as p$p"
            )->value("p$p");
        }

        return $results;
    }

    private function calculateHistogram(int $bins = 10): array
    {
        $stats = $this->query->selectRaw('MIN(computed) as min, MAX(computed) as max')->first();
        $width = ($stats->max - $stats->min) / $bins;
        $results = [];

        for ($i = 0; $i < $bins; $i++) {
            $min = $stats->min + ($width * $i);
            $max = $min + $width;
            $results[] = [
                'range' => [$min, $max],
                'count' => $this->query->whereBetween('computed', [$min, $max])->count(),
            ];
        }

        return $results;
    }

    public function getQuery(): Builder
    {
        return $this->query;
    }
}
