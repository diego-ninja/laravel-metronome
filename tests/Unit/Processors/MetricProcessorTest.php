<?php

namespace Tests\Unit\Processors;

use InvalidArgumentException;
use Ninja\Metronome\Dto\DimensionCollection;
use Ninja\Metronome\Dto\Key;
use Ninja\Metronome\Dto\Metadata;
use Ninja\Metronome\Dto\Value\AverageMetricValue;
use Ninja\Metronome\Dto\Value\CounterMetricValue;
use Ninja\Metronome\Dto\Value\GaugeMetricValue;
use Ninja\Metronome\Dto\Value\HistogramMetricValue;
use Ninja\Metronome\Dto\Value\PercentageMetricValue;
use Ninja\Metronome\Dto\Value\RateMetricValue;
use Ninja\Metronome\Dto\Value\SummaryMetricValue;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\Bucket;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Enums\Quantile;
use Ninja\Metronome\Metrics\Storage\Contracts\MetricStorage;
use Ninja\Metronome\Processors\Contracts\Processable;
use Ninja\Metronome\Processors\Items\Metric;
use Ninja\Metronome\Processors\MetricProcessor;
use Ninja\Metronome\Repository\Builder\Contracts\MetricAggregationRepository;
use Ninja\Metronome\ValueObjects\TimeWindow;

beforeEach(function () {
    $this->storage = mock(MetricStorage::class);
    $this->repository = mock(MetricAggregationRepository::class);
    $this->processor = new MetricProcessor($this->storage, $this->repository);
});

it('processes valid metric item', function () {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);
    $key = new Key(
        name: 'test_metric',
        type: MetricType::Counter,
        window: $window->aggregation,
        dimensions: new DimensionCollection([]),
    );

    $item = new Metric($key, $window);
    $metricValue = new CounterMetricValue(1.0);

    $this->storage->expects('value')
        ->with($key)
        ->andReturn($metricValue);

    $this->repository->expects('store')
        ->withAnyArgs()
        ->once();

    $this->processor->process($item);
});


it('skips processing when no value is found', function () {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);
    $key = new Key(
        name: 'test_metric',
        type: MetricType::Counter,
        window: $window->aggregation,
        dimensions: new DimensionCollection([]),
    );

    $item = new Metric($key, $window);

    $this->storage->expects('value')
        ->with($key)
        ->andReturn(CounterMetricValue::empty());

    $this->repository->expects('store')
        ->never();

    $this->processor->process($item);
});

it('throws exception for invalid processable type', function () {
    $invalidItem = new class implements Processable
    {
        public function identifier(): string
        {
            return 'test';
        }

        public function metadata(): Metadata
        {
            return new Metadata([]);
        }
    };

    expect(fn () => $this->processor->process($invalidItem))
        ->toThrow(InvalidArgumentException::class);
});

it('handles different metric types correctly', function (MetricType $type) {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);
    $key = new Key(
        name: 'test_metric',
        type: $type,
        window: $window->aggregation,
        dimensions: new DimensionCollection([]),
    );


    $item = new Metric($key, $window);
    $metricValue = match ($type) {
        MetricType::Counter => new CounterMetricValue(1.0),
        MetricType::Gauge => new GaugeMetricValue(1.0),
        MetricType::Histogram => new HistogramMetricValue(1.0, Bucket::Default->scale()),
        MetricType::Summary => new SummaryMetricValue(1.0, Quantile::scale()),
        MetricType::Average => new AverageMetricValue(1.0, 1.0, 1),
        MetricType::Rate => new RateMetricValue(1.0, 60),
        MetricType::Percentage => new PercentageMetricValue(1.0, 2.0),
        MetricType::Unknown => throw new InvalidArgumentException('Invalid metric type'),
    };

    $this->storage->expects('value')
        ->with($key)
        ->andReturn($metricValue);

    $this->repository->expects('store')
        ->withAnyArgs()
        ->once();

    $this->processor->process($item);
})->with(MetricType::all());
