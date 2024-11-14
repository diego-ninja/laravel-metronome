<?php

namespace Tests\Unit;

use Ninja\Metronome\Dto\DimensionCollection;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\MetricAggregator;
use Ninja\Metronome\Metrics\Registry;
use Ninja\Metronome\Metrics\Storage\Contracts\MetricStorage;
use Tests\Unit\Mocks\Definition\AverageMockDefinition;
use Tests\Unit\Mocks\Definition\CounterMockDefinition;
use Tests\Unit\Mocks\Definition\GaugeMockDefinition;
use Tests\Unit\Mocks\Definition\HistogramMockDefinition;
use Tests\Unit\Mocks\Definition\PercentageMockDefinition;
use Tests\Unit\Mocks\Definition\RateMockDefinition;

beforeEach(function () {
    $this->storage = mock(MetricStorage::class);
    $this->aggregator = new MetricAggregator($this->storage);
});

beforeEach(function () {
    Registry::register(CounterMockDefinition::create());
    Registry::register(GaugeMockDefinition::create());
    Registry::register(HistogramMockDefinition::create());
    Registry::register(AverageMockDefinition::create());
    Registry::register(RateMockDefinition::create());
    Registry::register(PercentageMockDefinition::create());
});

it('records counter metric', function () {
    $name = 'test_counter';
    $dimensions = new DimensionCollection([]);

    $this->storage->expects('store')->times(2); // For Realtime and Hourly windows

    $this->aggregator->counter($name, 1.0, $dimensions->array());
});

it('records gauge metric', function () {
    $name = 'test_gauge';
    $dimensions = new DimensionCollection([]);

    $this->storage->expects('store')->times(2);

    $this->aggregator->gauge($name, 1.0, $dimensions->array());
});

it('records histogram metric', function () {
    $name = 'test_histogram';
    $dimensions = new DimensionCollection([]);

    $this->storage->expects('store')->times(2);

    $this->aggregator->histogram($name, 1.0, $dimensions->array());
});

it('records average metric', function () {
    $name = 'test_average';
    $dimensions = new DimensionCollection([]);

    $this->storage->expects('store')->times(2);

    $this->aggregator->average($name, 1.0, $dimensions->array());
});

it('records rate metric', function () {
    $name = 'test_rate';
    $dimensions = new DimensionCollection([]);

    $this->storage->expects('store')->times(2);

    $this->aggregator->rate($name, 1.0, $dimensions->array());
});

it('records percentage metric', function () {
    $name = 'test_percentage';
    $dimensions = new DimensionCollection([]);

    $this->storage->expects('store')->times(2);

    $this->aggregator->percentage($name, 1.0, 2.0, $dimensions->array());
});

it('validates metric type before recording', function () {
    $name = 'test_counter';
    $dimensions = new DimensionCollection([]);

    $this->storage->expects('store')->never();

    expect(fn () => $this->aggregator->gauge($name, -1.0, $dimensions->array()))
        ->toThrow(\InvalidArgumentException::class);
});

it('checks window enablement', function () {
    expect($this->aggregator->enabled(Aggregation::Realtime))->toBeTrue()
        ->and($this->aggregator->enabled(Aggregation::Yearly))->toBeFalse();
});
