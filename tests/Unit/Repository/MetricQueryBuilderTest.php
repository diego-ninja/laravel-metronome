<?php

namespace Tests\Unit\Repository;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Ninja\Metronome\Dto\DimensionCollection;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Repository\MetricQueryBuilder;
use Ninja\Metronome\ValueObjects\TimeRange;

beforeEach(function () {
    $this->builder = mock(Builder::class);
    $this->builder->allows('andReturnSelf')->andReturnSelf();
    $this->queryBuilder = new MetricQueryBuilder($this->builder);
});

it('builds complex query with multiple filters', function () {
    $dimensions = new DimensionCollection([
        ['name' => 'host', 'value' => 'test-host'],
    ]);

    $timeRange = new TimeRange(
        from: now()->subHour(),
        to: now()
    );

    $this->builder->expects('where')->times(3)->andReturnSelf();
    $this->builder->expects('whereBetween')->once()->andReturnSelf();

    $this->queryBuilder
        ->withName('test_metric')
        ->withType(MetricType::Counter)
        ->withWindow(Aggregation::Realtime)
        ->withDimensions($dimensions)
        ->withTimeRange($timeRange);
});

it('handles complex grouping operations', function () {
    $this->builder->expects('addSelect')->twice()->andReturnSelf();
    $this->builder->expects('groupBy')->twice()->andReturnSelf();

    $this->queryBuilder
        ->groupByDimension('host')
        ->groupByTimeWindow('1 hour');
});

it('builds advanced analytics queries', function () {
    $this->builder->expects('addSelect')->times(3)->andReturnSelf();
    $this->builder->expects('join')->once()->andReturnSelf();

    $this->queryBuilder
        ->withChangeRate()
        ->withCorrelatedMetrics('other_metric', 0.7)
        ->aggregate('AVG', ['computed']);
});

it('handles query execution methods', function () {
    $mockCollection = new Collection([
        ['name' => 'test', 'value' => 1],
        ['name' => 'test2', 'value' => 2],
    ]);

    $this->builder->allows('get')->andReturn($mockCollection);
    $this->builder->allows('first')->andReturn($mockCollection->first());
    $this->builder->allows('count')->andReturn(2);

    expect($this->queryBuilder->get())->toHaveCount(2)
        ->and($this->queryBuilder->first())->toHaveKey('name')
        ->and($this->queryBuilder->count())->toBe(2);
});

it('validates input parameters', function () {
    expect(fn () => $this->queryBuilder->groupByTimeWindow('invalid'))
        ->toThrow(\InvalidArgumentException::class)
        ->and(fn () => $this->queryBuilder->wherePercentile(101))
        ->toThrow(\InvalidArgumentException::class)
        ->and(fn () => $this->queryBuilder->aggregate('INVALID', ['field']))
        ->toThrow(\InvalidArgumentException::class);
});
