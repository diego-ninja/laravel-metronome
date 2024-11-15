<?php

namespace Ninja\Metronome\Repository;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ninja\Metronome\Dto\DimensionCollection;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Repository\Builder\MetricQueryBuilder;
use Ninja\Metronome\Repository\Contracts\MetricAggregationRepository;
use Ninja\Metronome\Repository\Dto\Metric;
use Ninja\Metronome\Repository\Dto\MetricCriteria;
use Ninja\Metronome\ValueObjects\TimeRange;

class DatabaseMetricAggregationRepository implements MetricAggregationRepository
{
    public const METRIC_AGGREGATION_TABLE = 'metronome';

    public function query(): MetricQueryBuilder
    {
        return new MetricQueryBuilder($this->table());
    }

    public function store(Metric $metric): void
    {
        $this->table()->updateOrInsert(
            ['metric_fingerprint' => $metric->fingerprint()],
            [
                'name' => $metric->name,
                'type' => $metric->type->value,
                'window' => $metric->aggregation->value,
                'dimensions' => $metric->dimensions->toJson(),
                'timestamp' => $metric->timestamp,
                'value' => $metric->value->serialize(),
                'computed' => $metric->value->value(),
                'metadata' => json_encode($metric->value->metadata()),
                'updated_at' => now(),
            ]
        );
    }

    public function findByCriteria(MetricCriteria $criteria): Collection
    {
        return $this->query()
            ->withName($criteria->name)
            ->withTypes($criteria->types)
            ->withDimensions($criteria->dimensions)
            ->forAggregation($criteria->window)
            ->withTimeRange($criteria->timeRange)
            ->orderByTimestamp()
            ->get();
    }

    public function findAggregatedByType(MetricType $type, Aggregation $aggregation): Collection
    {
        return $this->query()
            ->withType($type)
            ->forAggregation($aggregation)
            ->withTimeRange(new TimeRange(
                from: now()->sub($aggregation->retention()),
                to: now()
            ))
            ->orderByTimestamp()
            ->get();
    }

    public function hasMetrics(Aggregation $aggregation): bool
    {
        return $this->query()
            ->forAggregation($aggregation)
            ->withTimeRange(new TimeRange(
                from: now()->sub($aggregation->retention()),
                to: now()
            ))
            ->count() > 0;
    }

    public function prune(Aggregation $window): int
    {
        return $this->query()
            ->forAggregation($window)
            ->withTimeRange(new TimeRange(
                from: Carbon::createFromTimestamp(0),
                to: now()->sub($window->retention())
            ))
            ->delete();

    }

    // Ejemplos de métodos adicionales útiles usando el QueryBuilder

    public function findByDimensions(string $name, DimensionCollection $dimensions): Collection
    {
        return $this->query()
            ->withName($name)
            ->withDimensions($dimensions)
            ->orderByTimestamp()
            ->get();
    }

    public function getMetricTrends(string $name, Aggregation $window): Collection
    {
        return $this->query()
            ->withName($name)
            ->forAggregation($window)
            ->withChangeRate()
            ->orderByTimestamp()
            ->get();
    }

    public function getDimensionStats(string $name, string $dimension): Collection
    {
        return $this->query()
            ->withName($name)
            ->groupByDimension($dimension)
            ->get();
    }

    public function getCorrelatedMetrics(string $name, float $threshold = 0.7): Collection
    {
        return $this->query()
            ->withName($name)
            ->withCorrelatedMetrics($name, $threshold)
            ->get();
    }

    public function getTimeSeriesData(
        string $name,
        Aggregation $window,
        string $interval = '1 hour'
    ): Collection {
        return $this->query()
            ->withName($name)
            ->forAggregation($window)
            ->groupByAggregation($interval)
            ->orderByTimestamp()
            ->get();
    }

    public function getTopPercentileMetrics(
        string $name,
        float $percentile = 95
    ): Collection {
        return $this->query()
            ->withName($name)
            ->wherePercentile($percentile, '>=')
            ->orderByComputed('desc')
            ->get();
    }

    private function table(): Builder
    {
        $table = config('metronome.table_name', self::METRIC_AGGREGATION_TABLE);

        return DB::connection($this->connection())->table($table);
    }

    private function connection(): string
    {
        return config('metronome.storage.metrics.persistent.connection', config('database.default'));
    }
}
