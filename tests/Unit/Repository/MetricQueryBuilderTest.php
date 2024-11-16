<?php

namespace Tests\Unit\Repository;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Mockery;
use Ninja\Metronome\Dto\Dimension;
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

        $this->queryBuilder->forAggregation(Aggregation::Realtime);
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

        $this->queryBuilder->withDimension(new Dimension('host', 'test-host'));
    });

    it('builds multiple dimensions filter', function () {
        $dimensions = new DimensionCollection([
            new Dimension('host', 'test-host'),
            new Dimension('env', 'prod'),
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
            ->once()
            ->andReturn(new Expression($expr));

        $this->builder->shouldReceive('groupBy')
            ->with(Mockery::type(Expression::class))
            ->once()
            ->andReturnSelf();

        $this->queryBuilder->groupByDimension('host');
    });

    it('groups by aggregation', function () {
        $this->builder->shouldReceive('groupByRaw')
            ->with(MetricQueryBuilder::VALID_TIME_WINDOWS[Aggregation::Hourly->value])
            ->once()
            ->andReturnSelf();

        $this->queryBuilder->groupByAggregation(Aggregation::Hourly);
    });
});

describe('joins', function () {
    beforeEach(function () {
        // Mock para el join builder
        $mockJoinBuilder = Mockery::mock(Builder::class);
        $mockJoinBuilder->shouldReceive('on')
            ->withArgs(function ($first, $operator, $second) {
                return str_contains($first, 'timestamp') &&
                    $operator === '=' &&
                    str_contains($second, 'timestamp');
            })
            ->andReturnSelf();

        $mockJoinBuilder->shouldReceive('where')
            ->withArgs(function ($column, $operator, $value) {
                return str_contains($column, 'name') &&
                    $operator === '=' &&
                    is_string($value);
            })
            ->andReturnSelf();

        // Mock para el query builder principal
        $this->builder->shouldReceive('joins')->andReturn([]);
    });

    it('joins metrics with basic join', function () {
        $this->builder->shouldReceive('innerJoin')
            ->once()
            ->withArgs(function ($table, $callback) {
                // Verificar la tabla
                if ($table !== 'metronome_metrics as m2') {
                    return false;
                }

                // Verificar que el callback es una closure
                if (! ($callback instanceof \Closure)) {
                    return false;
                }

                // La closure debería funcionar con nuestro mock
                $mockJoinBuilder = Mockery::mock(Builder::class);
                $mockJoinBuilder->shouldReceive('on')->andReturnSelf();
                $mockJoinBuilder->shouldReceive('where')->andReturnSelf();
                $callback($mockJoinBuilder);

                return true;
            })
            ->andReturnSelf();

        $this->queryBuilder->joinMetrics('other_metric');
    });

    it('joins metrics with custom alias', function () {
        $this->builder->shouldReceive('innerJoin')
            ->once()
            ->withArgs(function ($table, $callback) {
                if ($table !== 'metronome_metrics as custom') {
                    return false;
                }

                if (! ($callback instanceof \Closure)) {
                    return false;
                }

                $mockJoinBuilder = Mockery::mock(Builder::class);
                $mockJoinBuilder->shouldReceive('on')->andReturnSelf();
                $mockJoinBuilder->shouldReceive('where')->andReturnSelf();
                $callback($mockJoinBuilder);

                return true;
            })
            ->andReturnSelf();

        $this->queryBuilder->joinMetrics('other_metric', 'custom');
    });

    it('joins multiple related metrics', function () {
        $this->builder->shouldReceive('innerJoin')
            ->twice()
            ->withArgs(function ($table, $callback) {
                return str_contains($table, 'metronome_metrics as m') &&
                    $callback instanceof \Closure;
            })
            ->andReturnSelf();

        $this->queryBuilder->joinRelatedMetrics(['metric1', 'metric2']);
    });
});
describe('advanced filters', function () {
    it('filters by percentile', function () {
        $this->builder->shouldReceive('count')->once()->andReturn(100);
        $this->builder->shouldReceive('newQuery')->once()->andReturnSelf();
        $this->builder->shouldReceive('select')->once()->andReturnSelf();
        $this->builder->shouldReceive('orderBy')->once()->andReturnSelf();
        $this->builder->shouldReceive('limit')->once()->andReturnSelf();
        $this->builder->shouldReceive('offset')->once()->andReturnSelf();
        $this->builder->shouldReceive('where')->once()->andReturnSelf();

        $this->queryBuilder->wherePercentile(95);
    });

    it('filters with correlated metrics', function () {
        $this->builder->shouldReceive('innerJoin')->once()->andReturnSelf();
        $this->builder->shouldReceive('select')->once()->andReturnSelf();
        $this->builder->shouldReceive('having')->once()->andReturnSelf();
        $this->builder->shouldReceive('groupBy')->once()->andReturnSelf();

        $this->queryBuilder->withCorrelatedMetrics('other_metric', 0.7);
    });

    it('adds change rate calculation', function () {
        $this->builder->shouldReceive('raw')
            ->with('((computed - LAG(computed) OVER (ORDER BY timestamp)) / LAG(computed) OVER (ORDER BY timestamp)) * 100 as change_rate')
            ->andReturn(new Expression('change_rate_expression'));

        $this->builder->shouldReceive('addSelect')
            ->once()
            ->withArgs(function ($arg) {
                return $arg instanceof Expression;
            })
            ->andReturnSelf();

        $this->queryBuilder->withChangeRate();
    });

    it('builds metric stats query', function () {
        $this->builder->shouldReceive('count')
            ->andReturn(100);

        $this->builder->shouldReceive('avg')
            ->once()
            ->andReturn(50.5);

        $this->builder->shouldReceive('min')
            ->once()
            ->andReturn(1.0);

        $this->builder->shouldReceive('max')
            ->once()
            ->andReturn(100.0);

        $this->builder->shouldReceive('selectRaw')
            ->once()
            ->with('STDDEV(computed) as stddev')
            ->andReturnSelf();

        $this->builder->shouldReceive('value')->once()
            ->with('stddev')
            ->andReturn('10.5');

        $this->builder->shouldReceive('selectRaw')
            ->with(Mockery::pattern('/PERCENTILE_CONT/'))
            ->andReturnSelf();

        $this->builder->shouldReceive('value')
            ->with(Mockery::pattern('/p\d+/'))
            ->andReturn('50.0');

        // Mock para calculateHistogram
        $this->builder->shouldReceive('selectRaw')
            ->with('MIN(computed) as min, MAX(computed) as max')
            ->andReturnSelf();

        $this->builder->shouldReceive('first')
            ->andReturn((object) [
                'min' => 0,
                'max' => 100,
            ]);

        $this->builder->shouldReceive('whereBetween')
            ->andReturnSelf();
        $this->builder->shouldReceive('selectRaw')
            ->with(Mockery::pattern('/COUNT.*AVG.*MIN.*MAX.*STDDEV/'))
            ->andReturnSelf();

        $this->builder->shouldReceive('first')
            ->andReturn((object) [
                'count' => 100,
                'avg' => 50.5,
                'min' => 1.0,
                'max' => 100.0,
                'stddev' => 10.5,
            ]);

        $stats = $this->queryBuilder->stats();

        expect($stats)
            ->toHaveKeys(['count', 'avg', 'min', 'max', 'stddev', 'percentiles', 'histogram']);
    });
});
