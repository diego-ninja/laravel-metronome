<?php

namespace Ninja\Metronome\Traits;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Dto\DimensionCollection;
use Ninja\Metronome\Dto\Value\GaugeMetricValue;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Repository\Dto\Metric;
use ReflectionClass;

trait ProvidesMetrics
{
    public function metric(): Metric
    {
        return new Metric(
            name: $this->metricName(),
            type: $this->metricType(),
            value: $this->metricValue(),
            timestamp: $this->metricTimestamp(),
            dimensions: $this->metricDimensions(),
            aggregation: $this->metricAggregation()
        );
    }

    public function metricName(): string
    {
        return property_exists($this, 'metricName')
            ? $this->metricName
            : Str::snake((new ReflectionClass($this))->getShortName());
    }

    public function metricType(): MetricType
    {
        return property_exists($this, 'metricType')
            ? $this->metricType
            : MetricType::Gauge;
    }

    public function metricDimensions(): DimensionCollection
    {
        return property_exists($this, 'metricDimensions')
            ? new DimensionCollection($this->metricDimensions)
            : new DimensionCollection();
    }

    public function metricValue(): MetricValue
    {
        return property_exists($this, 'metricValue')
            ? $this->metricValue
            : new GaugeMetricValue(1.0);
    }

    public function metricTimestamp(): Carbon
    {
        return property_exists($this, 'metricTimestamp')
            ? $this->metricTimestamp
            : Carbon::now();
    }

    public function metricAggregation(): Aggregation
    {
        return property_exists($this, 'metricAggregation')
            ? $this->metricAggregation
            : Aggregation::Realtime;
    }
}