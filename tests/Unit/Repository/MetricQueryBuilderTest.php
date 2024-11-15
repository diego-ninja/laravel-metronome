<?php

namespace Tests\Unit\Repository;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use Mockery;
use Ninja\Metronome\Dto\DimensionCollection;
use Ninja\Metronome\Dto\Value\CounterMetricValue;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Repository\Builder\MetricQueryBuilder;

beforeEach(function () {
    $this->builder = Mockery::mock(Builder::class);
    $this->builder->shouldReceive('raw')->byDefault()->andReturnUsing(fn ($expr) => new Expression($expr));
    $this->queryBuilder = new MetricQueryBuilder($this->builder);
});

describe('basic filters', function () {
    it('builds name filter', function () {
        $this->builder->expects('where')
            ->with('name', 'test_metric')
            ->andReturnSelf();

        $this->queryBuilder->withName('test_metric');
    });

    it('builds type filter', function () {
        $this->builder->expects('where')
            ->with('type', MetricType::Counter->value)
            ->andReturnSelf();

        $this->queryBuilder->withType(MetricType::Counter);
    });

    it('builds window filter', function () {
        $this->builder->expects('where')
            ->with('window', Aggregation::Realtime->value)
            ->andReturnSelf();

        $this->queryBuilder->withWindow(Aggregation::Realtime);
    });

    it('builds multiple type filter', function () {
        $types = [MetricType::Counter, MetricType::Gauge];

        $this->builder->shouldReceive('whereIn')
            ->once()
            ->with('type', ['counter', 'gauge'])
            ->andReturnSelf();

        $this->queryBuilder->withTypes($types);
    });
});

describe('dimensions', function () {
    it('builds single dimension filter', function () {
        $this->builder->expects('where')
            ->with('dimensions', 'like', '%"host":"test-host"%')
            ->andReturnSelf();

        $this->queryBuilder->withDimension('host', 'test-host');
    });

    it('builds multiple dimensions filter', function () {
        $dimensions = new DimensionCollection([
            ['name' => 'host', 'value' => 'test-host'],
            ['name' => 'env', 'value' => 'prod'],
        ]);

        $this->builder->shouldReceive('where')
            ->twice()
            ->andReturnSelf();

        $this->queryBuilder->withDimensions($dimensions);
    });
});

describe('aggregations', function () {
    it('calculates count', function () {
        $this->builder->shouldReceive('count')
            ->once()
            ->andReturn(10);

        expect($this->queryBuilder->count())->toBe(10);
    });

    it('calculates sum', function () {
        $this->builder->shouldReceive('sum')
            ->once()
            ->with('computed')
            ->andReturn('100.5');

        expect($this->queryBuilder->sum())->toBe(100.5);
    });

    it('calculates average', function () {
        $this->builder->shouldReceive('avg')
            ->once()
            ->with('computed')
            ->andReturn('50.5');

        expect($this->queryBuilder->avg())->toBe(50.5);
    });

    it('calculates minimum value', function () {
        $this->builder->shouldReceive('min')
            ->once()
            ->with('computed')
            ->andReturn('1.0');

        expect($this->queryBuilder->min())->toBe(1.0);
    });

    it('calculates maximum value', function () {
        $this->builder->shouldReceive('max')
            ->once()
            ->with('computed')
            ->andReturn('100.0');

        expect($this->queryBuilder->max())->toBe(100.0);
    });

    it('aggregate by function', function () {
        $expected = [
            'count' => 100,
            'avg' => 50.5,
            'min' => 1.0,
            'max' => 100.0,
            'stddev' => 10.5,
        ];

        foreach ($expected as $function => $value) {
            $this->builder->shouldReceive('addSelect')
                ->once()
                ->andReturnSelf();
            $this->queryBuilder->aggregate($function, ['computed']);
        }

    });
});

describe('metric execution', function () {
    it('retrieves collection of metrics', function () {
        $mockRow = (object) [
            'name' => 'test_metric',
            'type' => MetricType::Counter->value,
            'value' => new CounterMetricValue(1.0),
            'computed' => 1.0,
            'dimensions' => '[]',
            'timestamp' => now(),
            'window' => Aggregation::Realtime->value,
            'metadata' => '{}',
        ];

        $this->builder->expects('get')
            ->andReturn(collect([$mockRow]));

        $result = $this->queryBuilder->get();
        expect($result)->toHaveCount(1);
    });
});

describe('metric grouping', function () {
    it('groups by dimension', function () {
        $expr = 'JSON_UNQUOTE(JSON_EXTRACT(dimensions, \'$.host\'))';

        $this->builder->shouldReceive('raw')
            ->with($expr)
            ->andReturn(new Expression($expr));

        $this->builder->shouldReceive('groupBy')
            ->with(Mockery::type(Expression::class))
            ->andReturnSelf();

        $this->queryBuilder->groupByDimension('host');
    });

    it('groups by time window', function () {
        $this->builder->shouldReceive('groupBy')
            ->with(Mockery::type(Expression::class))
            ->andReturnSelf();

        $this->queryBuilder->groupByTimeWindow('1 hour');
    });

    it('validates time window interval', function () {
        expect(fn () => $this->queryBuilder->groupByTimeWindow('invalid'))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('joins', function () {
    beforeEach(function () {
        $mockJoinBuilder = Mockery::mock(Builder::class);
        $mockJoinBuilder->shouldReceive('on')
            ->andReturnSelf();
        $mockJoinBuilder->shouldReceive('where')
            ->andReturnSelf();
    });

    it('joins metrics with basic join', function () {
        $this->builder->shouldReceive('innerJoin')
            ->once()
            ->withArgs(function ($table, $closure) {
                return $table === 'metronome_metrics AS m2';
            })
            ->andReturnSelf();

        $this->queryBuilder->joinMetrics('other_metric');
    });

    it('joins metrics with custom alias', function () {
        $this->builder->shouldReceive('innerJoin')
            ->once()
            ->withArgs(function ($table, $closure) {
                return $table === 'metronome_metrics AS custom';
            })
            ->andReturnSelf();

        $this->queryBuilder->joinMetrics('other_metric', 'custom');
    });
});

describe('advanced filters', function () {
    it('filters by percentile', function () {
        $this->builder->shouldReceive('count')->andReturn(100);
        $this->builder->shouldReceive('newQuery')->andReturnSelf();
        $this->builder->shouldReceive('select')->andReturnSelf();
        $this->builder->shouldReceive('orderBy')->andReturnSelf();
        $this->builder->shouldReceive('limit')->andReturnSelf();
        $this->builder->shouldReceive('offset')->andReturnSelf();
        $this->builder->shouldReceive('where')->andReturnSelf();

        $this->queryBuilder->wherePercentile(95);
    });

    it('filters with correlated metrics', function () {
        $this->builder->shouldReceive('innerJoin')->andReturnSelf();
        $this->builder->shouldReceive('select')->andReturnSelf();
        $this->builder->shouldReceive('having')->andReturnSelf();
        $this->builder->shouldReceive('groupBy')->andReturnSelf();

        $this->queryBuilder->withCorrelatedMetrics('other_metric', 0.7);
    });

    it('adds change rate calculation', function () {
        $this->builder->shouldReceive('addSelect')
            ->withArgs(function ($columns) {
                return is_array($columns) &&
                    $columns[1] instanceof Expression &&
                    str_contains($columns[1]->getValue(), 'LAG(computed)');
            })
            ->andReturnSelf();

        $this->queryBuilder->withChangeRate();
    });
});

describe('calculations', function () {
    it('calculates percentiles', function () {
        $this->builder->shouldReceive('selectRaw')
            ->andReturnSelf();

        $this->builder->shouldReceive('value')
            ->andReturn('50.5');

        $percentiles = $this->queryBuilder->calculatePercentiles();

        expect($percentiles)
            ->toHaveKeys(['p25', 'p50', 'p75', 'p90', 'p95', 'p99']);
    });

    it('calculates histogram', function () {
        $this->builder->shouldReceive('selectRaw')
            ->andReturnSelf();

        $this->builder->shouldReceive('first')
            ->andReturn((object) [
                'min' => 0,
                'max' => 100,
            ]);

        $this->builder->shouldReceive('whereBetween')
            ->times(10)
            ->andReturnSelf();

        $this->builder->shouldReceive('count')
            ->times(10)
            ->andReturn(10);

        $histogram = $this->queryBuilder->calculateHistogram();

        expect($histogram)
            ->toBeArray()
            ->toHaveCount(10)
            ->each->toHaveKeys(['range', 'count']);
    });
});
