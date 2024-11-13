<?php

namespace Ninja\Metronome\Repository;

use Carbon\Carbon;
use DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Dto\Dimension;
use Ninja\Metronome\Dto\DimensionCollection;
use Ninja\Metronome\Dto\Value\AverageMetricValue;
use Ninja\Metronome\Dto\Value\CounterMetricValue;
use Ninja\Metronome\Dto\Value\GaugeMetricValue;
use Ninja\Metronome\Dto\Value\HistogramMetricValue;
use Ninja\Metronome\Dto\Value\PercentageMetricValue;
use Ninja\Metronome\Dto\Value\RateMetricValue;
use Ninja\Metronome\Dto\Value\SummaryMetricValue;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Metrics\Handlers\HandlerFactory;
use Ninja\Metronome\Repository\Contracts\MetricAggregationRepository;
use Ninja\Metronome\Repository\Dto\Metric;
use Ninja\Metronome\ValueObjects\TimeRange;
use Throwable;

class DatabaseMetricAggregationRepository implements MetricAggregationRepository
{
    public const METRIC_AGGREGATION_TABLE = 'metrics';

    public function store(Metric $metric): void
    {
        try {
            DB::table(self::METRIC_AGGREGATION_TABLE)->updateOrInsert(
                [
                    'metric_fingerprint' => $metric->fingerprint(),
                ],
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
        } catch (Throwable $e) {
            Log::error('Failed to store metric', [
                'metric' => $metric->array(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function query(): MetricQueryBuilder
    {
        return new MetricQueryBuilder(DB::table(self::METRIC_AGGREGATION_TABLE));
    }

    public function findAggregatedByType(MetricType $type, Aggregation $aggregation): Collection
    {
        return DB::table(self::METRIC_AGGREGATION_TABLE)
            ->where('type', $type->value)
            ->where('window', $aggregation->value)
            ->where('timestamp', '>=', now()->sub($aggregation->retention()))
            ->orderBy('timestamp')
            ->get()
            ->map(function (\stdClass $row) {
                return new Metric(
                    name: $row->name,
                    type: MetricType::from($row->type),
                    value: $this->buildValue(
                        MetricType::from($row->type),
                        $row->value,
                        json_decode($row->metadata, true)
                    ),
                    timestamp: Carbon::parse($row->timestamp),
                    dimensions: DimensionCollection::from(json_decode($row->dimensions, true)),
                    aggregation: Aggregation::from($row->window),
                );
            });
    }

    public function hasMetrics(Aggregation $aggregation): bool
    {
        return DB::table(self::METRIC_AGGREGATION_TABLE)
            ->where('window', $aggregation->value)
            ->where('timestamp', '>=', now()->sub($aggregation->retention()))
            ->exists();
    }

    public function prune(Aggregation $window): int
    {
        $before = now()->sub($window->retention());

        return DB::table(self::METRIC_AGGREGATION_TABLE)
            ->where('window', $window->value)
            ->where('timestamp', '<', $before)
            ->delete();
    }

    private function buildValue(MetricType $type, string $stored, ?array $metadata = null): MetricValue
    {
        try {
            $value = json_decode($stored, true);
            return HandlerFactory::compute($type, [
                ['value' => (float)( $value['value'] ?? $stored), 'metadata' => $metadata]
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to reconstruct metric value', [
                'type' => $type->value,
                'stored' => $stored,
                'metadata' => $metadata,
                'error' => $e->getMessage()
            ]);

            return match ($type) {
                MetricType::Counter => CounterMetricValue::empty(),
                MetricType::Gauge => GaugeMetricValue::empty(),
                MetricType::Histogram => HistogramMetricValue::empty(),
                MetricType::Summary => SummaryMetricValue::empty(),
                MetricType::Average => AverageMetricValue::empty(),
                MetricType::Rate => RateMetricValue::empty(),
                MetricType::Percentage => PercentageMetricValue::empty(),
            };
        }
    }
}
