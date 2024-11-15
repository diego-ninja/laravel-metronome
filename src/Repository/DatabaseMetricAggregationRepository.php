<?php

namespace Ninja\Metronome\Repository;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ninja\Metronome\Dto\DimensionCollection;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Repository\Builder\Builder\MetricQueryBuilder;
use Ninja\Metronome\Repository\Builder\Contracts\MetricAggregationRepository;
use Ninja\Metronome\Repository\Builder\Dto\Metric;
use Ninja\Metronome\ValueObjects\TimeRange;

class DatabaseMetricAggregationRepository implements MetricAggregationRepository
{
    public const METRIC_AGGREGATION_TABLE = 'device_metrics';

    public function query(): MetricQueryBuilder
    {
        return new MetricQueryBuilder(DB::table(self::METRIC_AGGREGATION_TABLE));
    }

    public function store(Metric $metric): void
    {
        DB::table(self::METRIC_AGGREGATION_TABLE)->updateOrInsert(
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

    public function findAggregatedByType(MetricType $type, Aggregation $aggregation): Collection
    {
        return $this->query()
            ->withType($type)
            ->withWindow($aggregation)
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
            ->withWindow($aggregation)
            ->withTimeRange(new TimeRange(
                from: now()->sub($aggregation->retention()),
                to: now()
            ))
            ->count() > 0;
    }

    public function prune(Aggregation $window): int
    {
        return $this->query()
            ->withWindow($window)
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
            ->withWindow($window)
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
            ->withWindow($window)
            ->groupByTimeWindow($interval)
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
            ->orderByValue('desc')
            ->get();
    }
}
