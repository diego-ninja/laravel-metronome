<?php

namespace Ninja\Metronome;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Dto\DimensionCollection;
use Ninja\Metronome\Dto\Key;
use Ninja\Metronome\Dto\Value\AverageMetricValue;
use Ninja\Metronome\Dto\Value\CounterMetricValue;
use Ninja\Metronome\Dto\Value\GaugeMetricValue;
use Ninja\Metronome\Dto\Value\HistogramMetricValue;
use Ninja\Metronome\Dto\Value\PercentageMetricValue;
use Ninja\Metronome\Dto\Value\RateMetricValue;
use Ninja\Metronome\Dto\Value\SummaryMetricValue;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Exceptions\InvalidMetricException;
use Ninja\Metronome\Metrics\Registry;
use Ninja\Metronome\Metrics\Storage\Contracts\MetricStorage;
use Ninja\Metronome\Repository\Dto\Metric;
use Throwable;

final readonly class MetricAggregator
{
    private Collection $windows;

    public function __construct(private MetricStorage $storage)
    {
        $this->windows = collect(config("metronome.aggregation.windows", [
            Aggregation::Realtime,
            Aggregation::Hourly
        ]));
    }

    /**
     * @throws InvalidMetricException
     */
    public function collect(Metric $metric): void
    {
        $this->record(
            name: $metric->name,
            type: $metric->type,
            value: $metric->value,
            dimensions: $metric->dimensions
        );
    }

    /**
     * @throws InvalidMetricException
     */
    public function counter(string $name, float $value = 1, ?array $dimensions = null): void
    {
        $definition = Registry::get($name);
        if (!$definition) {
            throw new InvalidMetricException(sprintf('Metric %s not found in registry', $name));
        }

        $this->record(
            name: $name,
            type: MetricType::Counter,
            value: new CounterMetricValue($value),
            dimensions: $dimensions ? DimensionCollection::from($dimensions) : DimensionCollection::empty()
        );
    }

    /**
     * @throws InvalidMetricException
     */
    public function gauge(string $name, float $value, ?array $dimensions = null): void
    {
        $definition = Registry::get($name);
        if (!$definition) {
            throw new InvalidMetricException(sprintf('Metric %s not found in registry', $name));
        }

        $this->record(
            name: $name,
            type: MetricType::Gauge,
            value: new GaugeMetricValue($value, time()),
            dimensions: $dimensions ? DimensionCollection::from($dimensions) : DimensionCollection::empty()
        );
    }

    /**
     * @throws InvalidMetricException
     */
    public function percentage(string $name, float $value, float $total, ?array $dimensions = null): void
    {
        $definition = Registry::get($name);
        if (!$definition) {
            throw new InvalidMetricException(sprintf('Metric %s not found in registry', $name));
        }

        $this->record(
            name: $name,
            type: MetricType::Percentage,
            value: new PercentageMetricValue($value, $total),
            dimensions: $dimensions ? DimensionCollection::from($dimensions) : DimensionCollection::empty()
        );
    }

    /**
     * @throws InvalidMetricException
     */
    public function histogram(string $name, float $value, ?array $dimensions = null): void
    {
        $definition = Registry::get($name);
        if (!$definition) {
            throw new InvalidMetricException(sprintf('Metric %s not found in registry', $name));
        }

        $this->record(
            name: $name,
            type: MetricType::Histogram,
            value: new HistogramMetricValue(
                value: $value,
                buckets: $definition->buckets()
            ),
            dimensions: $dimensions ? DimensionCollection::from($dimensions) : DimensionCollection::empty()
        );
    }

    /**
     * @throws InvalidMetricException
     */
    public function summary(string $name, float $value, ?array $dimensions = null): void
    {
        $definition = Registry::get($name);
        if (!$definition) {
            throw new InvalidMetricException(sprintf('Metric %s not found in registry', $name));
        }

        $this->record(
            name: $name,
            type: MetricType::Summary,
            value: new SummaryMetricValue(
                value: $value,
                quantiles: $definition->quantiles()
            ),
            dimensions: $dimensions ? DimensionCollection::from($dimensions) : DimensionCollection::empty()
        );
    }


    /**
     * @throws InvalidMetricException
     */
    public function average(string $name, float $value, ?array $dimensions = null): void
    {
        $definition = Registry::get($name);
        if (!$definition) {
            throw new InvalidMetricException(sprintf('Metric %s not found in registry', $name));
        }

        $this->record(
            name: $name,
            type: MetricType::Average,
            value: new AverageMetricValue($value),
            dimensions: $dimensions ? DimensionCollection::from($dimensions) : DimensionCollection::empty()
        );
    }

    /**
     * @throws InvalidMetricException
     */
    public function rate(string $name, float $value, array $dimensions = [], ?int $interval = null): void
    {
        $definition = Registry::get($name);
        if (!$definition) {
            throw new InvalidMetricException(sprintf('Metric %s not found in registry', $name));
        }

        $interval = $interval ?? config('devices.observability.rate_interval', 60);

        $this->record(
            name: $name,
            type: MetricType::Rate,
            value: new RateMetricValue($value, $interval),
            dimensions: $dimensions ? DimensionCollection::from($dimensions) : DimensionCollection::empty()
        );
    }

    /**
     * @throws InvalidMetricException
     */
    public function record(
        string $name,
        MetricType $type,
        MetricValue $value,
        DimensionCollection $dimensions
    ): void {
        Registry::validate($name, $type, $value, $dimensions);

        foreach ($this->windows as $window) {
            try {
                $this->storage->store(
                    new Key(
                        name: $name,
                        type: $type,
                        window: $window,
                        dimensions: $dimensions,
                        prefix: config('metronome.prefix')
                    ),
                    $value
                );
            } catch (Throwable $e) {
                Log::error('Failed to record metric', [
                    'name' => $name,
                    'type' => $type->value,
                    'window' => $window->value,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    public function windows(): Collection
    {
        return $this->windows;
    }

    public function enabled(Aggregation $window): bool
    {
        return $this->windows->contains($window);
    }
}
