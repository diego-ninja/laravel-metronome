<?php

namespace Tests\Unit\Repository;

use Illuminate\Database\Query\Builder;
use Mockery;
use Ninja\Metronome\Dto\DimensionCollection;
use Ninja\Metronome\Dto\Value\CounterMetricValue;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Repository\Builder\DatabaseMetricAggregationRepository;
use Ninja\Metronome\Repository\Builder\Dto\Metric;

beforeEach(function () {
    $this->builder = Mockery::mock(Builder::class);
    // Mockear el facade DB globalmente
    $db = Mockery::mock('alias:Illuminate\Support\Facades\DB');
    $db->shouldReceive('table')
        ->andReturn($this->builder);

    $this->repository = new DatabaseMetricAggregationRepository;
});

it('stores metric', function () {
    $metric = new Metric(
        name: 'test_metric',
        type: MetricType::Counter,
        value: new CounterMetricValue(1.0),
        timestamp: now(),
        dimensions: new DimensionCollection([]),
        aggregation: Aggregation::Realtime
    );

    $this->builder->shouldReceive('updateOrInsert')
        ->once()
        ->withArgs(function ($condition, $values) {
            return isset($values['name']) &&
                $values['name'] === 'test_metric' &&
                $values['type'] === MetricType::Counter->value;
        });

    $this->repository->store($metric);
});

it('finds aggregated metrics by type', function () {
    $window = Aggregation::Realtime;
    $type = MetricType::Counter;
    $timestamp = now();

    $mockRow = (object) [
        'name' => 'test_metric',
        'type' => $type->value,
        'value' => '1.0',
        'metadata' => '{"count":1}',
        'dimensions' => '[]',
        'timestamp' => $timestamp->toDateTimeString(),
        'window' => $window->value,
    ];

    $this->builder->shouldReceive('where')->with('type', $type->value)->andReturnSelf();
    $this->builder->shouldReceive('where')->with('window', $window->value)->andReturnSelf();
    $this->builder->shouldReceive('where')->with('timestamp', '>=', Mockery::any())->andReturnSelf();
    $this->builder->shouldReceive('orderBy')->with('timestamp')->andReturnSelf();
    $this->builder->shouldReceive('get')->andReturn(collect([$mockRow]));

    $metrics = $this->repository->findAggregatedByType($type, $window);

    expect($metrics)->toHaveCount(1)
        ->and($metrics->first())->toBeInstanceOf(Metric::class)
        ->and($metrics->first()->name)->toBe('test_metric')
        ->and($metrics->first()->type)->toBe($type)
        ->and($metrics->first()->aggregation)->toBe($window);
});

it('prunes old metrics', function () {
    $window = Aggregation::Daily;
    $expectedCount = 5;

    $this->builder->shouldReceive('where')->with('window', $window->value)->andReturnSelf();
    $this->builder->shouldReceive('where')->with('timestamp', '<', Mockery::any())->andReturnSelf();
    $this->builder->shouldReceive('delete')->andReturn($expectedCount);

    expect($this->repository->prune($window))->toBe($expectedCount);
});

it('checks for metrics existence', function () {
    $window = Aggregation::Realtime;

    $this->builder->shouldReceive('where')->with('window', $window->value)->andReturnSelf();
    $this->builder->shouldReceive('where')->with('timestamp', '>=', Mockery::any())->andReturnSelf();
    $this->builder->shouldReceive('exists')->andReturn(true);

    expect($this->repository->hasMetrics($window))->toBeTrue();
});
