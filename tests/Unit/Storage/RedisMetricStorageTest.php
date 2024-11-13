<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
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
use Ninja\Metronome\Metrics\Storage\RedisMetricStorage;
use Ninja\Metronome\ValueObjects\TimeWindow;

uses()->group('storage');

beforeEach(function () {
    $this->redis = Mockery::mock('Illuminate\Redis\Connections\Connection');
    Redis::shouldReceive('connection')
        ->with('metrics')
        ->andReturn($this->redis);

    $this->storage = new RedisMetricStorage('test_prefix', 'metrics');

    $this->redis->shouldReceive('pipeline')->byDefault()->andReturnUsing(function ($callback) {
        $pipe = Mockery::mock('Illuminate\Redis\Connections\Connection');
        $pipe->shouldReceive('incrbyfloat')->byDefault()->withAnyArgs();
        $pipe->shouldReceive('set')->byDefault()->withAnyArgs();
        $pipe->shouldReceive('zadd')->byDefault()->withAnyArgs();
        $pipe->shouldReceive('expire')->byDefault()->withAnyArgs();
        $callback($pipe);

        return null;
    });

    $this->redis->shouldReceive('keys')->byDefault()->andReturn([]);
});

afterEach(function () {
    Mockery::close();
});

it('stores counter metric value', function () {
    $key = new Key(
        name: 'test_metric',
        type: MetricType::Counter,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: time()
    );

    $value = new CounterMetricValue(10.5);

    $this->redis->shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function ($callback) {
            $pipe = Mockery::mock('Illuminate\Redis\Connections\Connection');
            $pipe->shouldReceive('incrbyfloat')
                ->withAnyArgs()
                ->once();
            $pipe->shouldReceive('expire')
                ->withAnyArgs()
                ->once();
            $callback($pipe);
        });

    $this->storage->store($key, $value);

    expect(true)->toBeTrue();
});

it('stores gauge metric value', function () {
    $now = Carbon::now();
    Carbon::setTestNow($now);

    $key = new Key(
        name: 'test_metric',
        type: MetricType::Gauge,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: time()
    );

    $value = new GaugeMetricValue(10.5);

    $this->redis->shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function ($callback) use ($now) {
            $pipe = Mockery::mock('Illuminate\Redis\Connections\Connection');
            $pipe->shouldReceive('set')
                ->withArgs(function ($key, $value) use ($now) {
                    $data = json_decode($value, true);

                    return str_contains($key, ':gauge:') &&
                        $data['value'] === 10.5 &&
                        $data['timestamp'] === $now->timestamp;
                })
                ->once();
            $pipe->shouldReceive('expire')->withAnyArgs()->once();
            $callback($pipe);
        });

    $this->storage->store($key, $value);
    expect(true)->toBeTrue();
});

it('stores histogram metric value', function () {
    $now = Carbon::now();
    Carbon::setTestNow($now);

    $buckets = [10, 50, 100];
    $key = new Key(
        name: 'test_metric',
        type: MetricType::Histogram,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: time()
    );

    $value = new HistogramMetricValue(
        value: 42.0,
        buckets: $buckets,
        count: 1,
        sum: 42.0
    );

    $this->redis->shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function ($callback) use ($now) {
            $pipe = Mockery::mock('Illuminate\Redis\Connections\Connection');
            $pipe->shouldReceive('zadd')
                ->withArgs(function ($key, $score, $value) use ($now) {
                    $data = json_decode($value, true);

                    return str_contains($key, ':histogram:')
                        && $score === $now->timestamp
                        && isset($data['value'])
                        && isset($data['timestamp'])
                        && isset($data['metadata']);
                })
                ->once();
            $pipe->shouldReceive('expire')->withAnyArgs()->once();
            $callback($pipe);
        });

    $this->storage->store($key, $value);
    expect(true)->toBeTrue();
});

it('stores summary metric value', function () {
    $now = Carbon::now();
    Carbon::setTestNow($now);

    $key = new Key(
        name: 'test_metric',
        type: MetricType::Summary,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: time()
    );

    $value = new SummaryMetricValue(
        value: 42.0,
        quantiles: [0.5, 0.9, 0.99],
        count: 1,
        sum: 42.0
    );

    $this->redis->shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function ($callback) use ($now) {
            $pipe = Mockery::mock('Illuminate\Redis\Connections\Connection');
            $pipe->shouldReceive('zadd')
                ->withArgs(function ($key, $score, $value) use ($now) {
                    $data = json_decode($value, true);

                    return str_contains($key, ':summary:')
                        && $score === $now->timestamp
                        && isset($data['value'])
                        && isset($data['timestamp'])
                        && isset($data['metadata']);
                })
                ->once();
            $pipe->shouldReceive('expire')->withAnyArgs()->once();
            $callback($pipe);
        });

    $this->storage->store($key, $value);
    expect(true)->toBeTrue();
});

it('stores average metric value', function () {
    $now = Carbon::now();
    Carbon::setTestNow($now);

    $key = new Key(
        name: 'test_metric',
        type: MetricType::Average,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: time()
    );

    $value = new AverageMetricValue(
        value: 15.0,
        sum: 30.0,
        count: 2
    );

    $this->redis->shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function ($callback) use ($now) {
            $pipe = Mockery::mock('Illuminate\Redis\Connections\Connection');
            $pipe->shouldReceive('zadd')
                ->withArgs(function ($key, $score, $value) use ($now) {
                    $data = json_decode($value, true);

                    return str_contains($key, ':average:')
                        && $score === $now->timestamp
                        && isset($data['value'])
                        && isset($data['timestamp'])
                        && isset($data['metadata'])
                        && isset($data['metadata']['sum'])
                        && isset($data['metadata']['count']);
                })
                ->once();
            $pipe->shouldReceive('expire')->withAnyArgs()->once();
            $callback($pipe);
        });

    $this->storage->store($key, $value);
    expect(true)->toBeTrue();
});

it('stores percentage metric value', function () {
    $now = Carbon::now();
    Carbon::setTestNow($now);

    $key = new Key(
        name: 'test_metric',
        type: MetricType::Percentage,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: time()
    );

    $value = new PercentageMetricValue(
        value: 75.0,
        total: 100.0,
        count: 1
    );

    $this->redis->shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function ($callback) use ($now) {
            $pipe = Mockery::mock('Illuminate\Redis\Connections\Connection');
            $pipe->shouldReceive('zadd')
                ->withArgs(function ($key, $score, $value) use ($now) {
                    $data = json_decode($value, true);

                    return str_contains($key, ':percentage:')
                        && $score === $now->timestamp
                        && isset($data['value'])
                        && isset($data['timestamp'])
                        && isset($data['metadata'])
                        && isset($data['metadata']['total'])
                        && isset($data['metadata']['count'])
                        && isset($data['metadata']['percentage']);
                })
                ->once();
            $pipe->shouldReceive('expire')->withAnyArgs()->once();
            $callback($pipe);
        });

    $this->storage->store($key, $value);
    expect(true)->toBeTrue();
});

it('stores rate metric value', function () {
    $now = Carbon::now();
    Carbon::setTestNow($now);

    $key = new Key(
        name: 'test_metric',
        type: MetricType::Rate,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: time()
    );

    $value = new RateMetricValue(
        value: 42.0,
        interval: 3600,
        count: 1
    );

    $this->redis->shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function ($callback) use ($now) {
            $pipe = Mockery::mock('Illuminate\Redis\Connections\Connection');
            $pipe->shouldReceive('zadd')
                ->withArgs(function ($key, $score, $value) use ($now) {
                    $data = json_decode($value, true);

                    return str_contains($key, ':rate:')
                        && $score === $now->timestamp
                        && isset($data['value'])
                        && isset($data['timestamp'])
                        && isset($data['metadata'])
                        && isset($data['metadata']['interval'])
                        && isset($data['metadata']['count']);
                })
                ->once();
            $pipe->shouldReceive('expire')->withAnyArgs()->once();
            $callback($pipe);
        });

    $this->storage->store($key, $value);
    expect(true)->toBeTrue();
});

test('each metric type is stored with correct Redis command', function (MetricType $type, string $expectedCommand) {
    $now = Carbon::now();
    Carbon::setTestNow($now);

    $key = new Key(
        name: 'test_metric',
        type: $type,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: time()
    );

    $value = match ($type) {
        MetricType::Counter => new CounterMetricValue(1.0),
        MetricType::Gauge => new GaugeMetricValue(1.0),
        MetricType::Histogram => new HistogramMetricValue(1.0, [10, 50, 100]),
        MetricType::Summary => new SummaryMetricValue(1.0, [0.5, 0.9, 0.99]),
        MetricType::Average => new AverageMetricValue(1.0, 1.0, 1),
        MetricType::Rate => new RateMetricValue(1.0, 3600),
        MetricType::Percentage => new PercentageMetricValue(1.0, 100.0, 1)
    };

    $this->redis->shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function ($callback) use ($expectedCommand) {
            $pipe = Mockery::mock('Illuminate\Redis\Connections\Connection');
            $pipe->shouldReceive($expectedCommand)
                ->withArgs(function (...$args) {
                    return true;
                })
                ->once();
            $pipe->shouldReceive('expire')->withAnyArgs()->once();
            $callback($pipe);
        });

    $this->storage->store($key, $value);
    expect(true)->toBeTrue();
})->with([
    [MetricType::Counter, 'incrbyfloat'],
    [MetricType::Gauge, 'set'],
    [MetricType::Histogram, 'zadd'],
    [MetricType::Summary, 'zadd'],
    [MetricType::Average, 'zadd'],
    [MetricType::Rate, 'zadd'],
    [MetricType::Percentage, 'zadd'],
]);

it('retrieves counter value correctly', function () {
    $key = new Key(
        name: 'test_metric',
        type: MetricType::Counter,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: time()
    );

    $this->redis->shouldReceive('get')
        ->once()
        ->andReturn('42.5');

    $value = $this->storage->value($key);

    expect($value)
        ->toBeInstanceOf(CounterMetricValue::class)
        ->and($value->value())->toBe(42.5);
});

it('retrieves gauge value correctly', function () {
    $now = time();
    $key = new Key(
        name: 'test_metric',
        type: MetricType::Gauge,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: $now
    );

    $storedValue = [
        'value' => 42.5,
        'timestamp' => $now,
    ];

    $this->redis->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($storedValue));

    $value = $this->storage->value($key);

    expect($value)
        ->toBeInstanceOf(GaugeMetricValue::class)
        ->and($value->value())->toBe(42.5)
        ->and($value->metadata()['timestamp'])->toBe($now);
});

it('retrieves histogram value correctly', function () {
    $now = time();
    $key = new Key(
        name: 'test_metric',
        type: MetricType::Histogram,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: $now
    );

    $storedValues = [
        [
            'value' => 10.0,
            'timestamp' => $now - 2,
        ],
        [
            'value' => 20.0,
            'timestamp' => $now - 1,
        ],
        [
            'value' => 30.0,
            'timestamp' => $now,
        ],
    ];

    $this->redis->shouldReceive('zrange')
        ->with(Mockery::any(), 0, -1, ['WITHSCORES' => true])
        ->once()
        ->andReturn(array_combine(
            array_map('json_encode', $storedValues),
            array_column($storedValues, 'timestamp')
        ));

    $value = $this->storage->value($key);

    expect($value)
        ->toBeInstanceOf(HistogramMetricValue::class)
        ->and($value->value())->toBe(20.0) // mean value
        ->and($value->metadata()['sum'])->toBe(60.0)
        ->and($value->metadata()['count'])->toBe(3);
});

it('retrieves summary value correctly', function () {
    $now = time();
    $key = new Key(
        name: 'test_metric',
        type: MetricType::Summary,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: $now
    );

    $storedValues = [
        [
            'value' => 10.0,
            'timestamp' => $now - 2,
        ],
        [
            'value' => 20.0,
            'timestamp' => $now - 1,
        ],
        [
            'value' => 30.0,
            'timestamp' => $now,
        ],
    ];

    $this->redis->shouldReceive('zrange')
        ->with(Mockery::any(), 0, -1, ['WITHSCORES' => true])
        ->once()
        ->andReturn(array_combine(
            array_map('json_encode', $storedValues),
            array_column($storedValues, 'timestamp')
        ));

    $value = $this->storage->value($key);

    expect($value)
        ->toBeInstanceOf(SummaryMetricValue::class)
        ->and($value->value())->toBe(20.0) // mean value
        ->and($value->metadata()['sum'])->toBe(60.0)
        ->and($value->metadata()['count'])->toBe(3)
        ->and($value->metadata()['quantiles'])->toHaveKeys(['0.5', '0.9', '0.99']);
});

it('retrieves average value correctly', function () {
    $now = time();
    $key = new Key(
        name: 'test_metric',
        type: MetricType::Average,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: $now
    );

    $storedValues = [
        [
            'value' => 10.0,
            'timestamp' => $now,
        ],
        [
            'value' => 20.0,
            'timestamp' => $now,
        ],
    ];

    $this->redis->shouldReceive('zrange')
        ->with(Mockery::any(), 0, -1, ['WITHSCORES' => true])
        ->once()
        ->andReturn(array_combine(
            array_map('json_encode', $storedValues),
            array_column($storedValues, 'timestamp')
        ));

    $value = $this->storage->value($key);

    expect($value)
        ->toBeInstanceOf(AverageMetricValue::class)
        ->and($value->value())->toBe(15.0)
        ->and($value->metadata()['sum'])->toBe(30.0)
        ->and($value->metadata()['count'])->toBe(2);
});

it('retrieves percentage value correctly', function () {
    $now = time();
    $key = new Key(
        name: 'test_metric',
        type: MetricType::Percentage,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: $now
    );

    $storedValues = [
        [
            'value' => 75.0,
            'timestamp' => $now,
            'metadata' => ['total' => 100.0],
        ],
        [
            'value' => 80.0,
            'timestamp' => $now,
            'metadata' => ['total' => 100.0],
        ],
    ];

    $this->redis->shouldReceive('zrange')
        ->withAnyArgs()
        ->once()
        ->andReturn(array_combine(
            array_map('json_encode', $storedValues),
            array_column($storedValues, 'timestamp')
        ));

    $value = $this->storage->value($key);

    expect($value)
        ->toBeInstanceOf(PercentageMetricValue::class)
        ->and($value->value())->toBe(155.0)
        ->and($value->metadata()['total'])->toBe(200.0);
});

it('retrieves rate value correctly', function () {
    $now = time();
    $key = new Key(
        name: 'test_metric',
        type: MetricType::Rate,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: $now
    );

    $storedValues = [
        [
            'value' => 1.0,
            'timestamp' => $now - 3600,
        ],
        [
            'value' => 1.0,
            'timestamp' => $now,
        ],
    ];

    $this->redis->shouldReceive('zrange')
        ->with(Mockery::any(), 0, -1, ['WITHSCORES' => true])
        ->once()
        ->andReturn(array_combine(
            array_map('json_encode', $storedValues),
            array_column($storedValues, 'timestamp')
        ));

    $value = $this->storage->value($key);

    expect($value)
        ->toBeInstanceOf(RateMetricValue::class)
        ->and($value->value())->toBe(1.0) // 1 event per hour
        ->and($value->metadata()['interval'])->toBe(3600)
        ->and($value->metadata()['count'])->toBe(2);
});

test('retrieving empty values returns appropriate defaults', function (MetricType $type) {
    $key = new Key(
        name: 'test_metric',
        type: $type,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: time()
    );

    $buckets = [10, 50, 100];
    $quantiles = [0.5, 0.9, 0.99];

    match ($type) {
        MetricType::Counter,
        MetricType::Gauge => $this->redis->shouldReceive('get')->once()->andReturn(null),
        MetricType::Histogram => $this->redis->shouldReceive('zrange')
            ->withAnyArgs()
            ->once()
            ->andReturn([json_encode(['value' => 0, 'metadata' => ['buckets' => $buckets]])]),
        MetricType::Summary => $this->redis->shouldReceive('zrange')
            ->withAnyArgs()
            ->once()
            ->andReturn([json_encode(['value' => 0, 'metadata' => ['quantiles' => $quantiles]])]),
        default => $this->redis->shouldReceive('zrange')
            ->withAnyArgs()
            ->once()
            ->andReturn([])
    };

    $value = $this->storage->value($key);

    expect($value->value())->toBe(0.0)
        ->and($value->metadata())->toBeArray();
})->with(fn () => [
    MetricType::Counter,
    MetricType::Gauge,
    MetricType::Histogram,
    MetricType::Summary,
    MetricType::Average,
    MetricType::Rate,
    MetricType::Percentage,
]);

it('returns empty value when key not found', function () {
    $key = new Key(
        name: 'test_metric',
        type: MetricType::Counter,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: time()
    );

    $this->redis->shouldReceive('get')
        ->once()
        ->andReturn(null);

    $value = $this->storage->value($key);

    expect($value)
        ->toBeInstanceOf(CounterMetricValue::class)
        ->and($value->value())->toBe(0.0);
});

it('finds keys matching pattern', function () {
    $pattern = '*:counter:realtime:*:*';

    $this->redis->shouldReceive('keys')
        ->with(Mockery::pattern('/^test_prefix/'))
        ->andReturn([
            'test_prefix:metric1:counter:realtime:123:',
            'test_prefix:metric2:counter:realtime:456:',
        ]);

    $keys = $this->storage->keys($pattern);

    expect($keys)->toHaveCount(2)
        ->and($keys)->toContain('metric1:counter:realtime:123:')
        ->and($keys)->toContain('metric2:counter:realtime:456:');
});

it('prunes old metrics', function () {
    $before = now()->subHour();
    $pattern = sprintf('test_prefix:*:%s:%d:*', Aggregation::Realtime->value, $before->timestamp);

    $this->redis->shouldReceive('keys')
        ->with($pattern)
        ->andReturn(['key1', 'key2']);

    $this->redis->shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function ($callback) {
            $pipe = Mockery::mock('Illuminate\Redis\Connections\Connection');
            $pipe->shouldReceive('del')->twice();
            $callback($pipe);
        });

    $count = $this->storage->prune(Aggregation::Realtime, $before);
    expect($count)->toBe(2);
});

it('checks expiration of time windows', function () {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);

    expect($this->storage->expired($window))->toBeFalse();

    Carbon::setTestNow(now()->addMinutes(2));
    expect($this->storage->expired($window))->toBeTrue();
});

it('reports health status', function () {
    // Mock para Redis::info()
    $this->redis->shouldReceive('info')
        ->withNoArgs()
        ->once()
        ->andReturn([
            'used_memory_human' => '1.5M',
            'used_memory' => 1500000,
            'used_memory_peak' => 2000000,
            'mem_fragmentation_ratio' => 1.2,
            'connected_clients' => 10,
            'total_commands_processed' => 1000,
            'instantaneous_ops_per_sec' => 100,
            'rejected_connections' => 0,
            'rdb_last_save_time' => time(),
        ]);

    $this->redis->shouldReceive('info')
        ->with('keyspace')
        ->once()
        ->andReturn(['db0' => ['keys' => 100]]);

    $this->redis->shouldReceive('keys')
        ->with('test_prefix:*')
        ->once()
        ->andReturn(array_fill(0, 50, 'key'));

    $health = $this->storage->health();

    expect($health)
        ->toHaveKey('status')
        ->and($health['status'])->toBe('healthy')
        ->and($health)->toHaveKeys(['used_memory', 'total_keys', 'metrics_keys'])
        ->and($health['metrics_keys'])->toBe(50);
});

test('storage handles multiple operations correctly', function () {
    $operations = collect(range(1, 5))->map(fn ($i) => [
        'key' => new Key(
            name: "test_metric{$i}",
            type: MetricType::Counter,
            window: Aggregation::Realtime,
            dimensions: new DimensionCollection,
            slot: time()
        ),
        'value' => new CounterMetricValue(1.0),
    ]);

    $this->redis->shouldReceive('pipeline')
        ->times(5)
        ->andReturnUsing(function ($callback) {
            $pipe = Mockery::mock('Illuminate\Redis\Connections\Connection');
            $pipe->shouldReceive('incrbyfloat')
                ->withAnyArgs()
                ->once();
            $pipe->shouldReceive('expire')
                ->withAnyArgs()
                ->once();
            $callback($pipe);
        });

    // En lugar de usar toThrow, simplemente ejecutamos las operaciones
    foreach ($operations as $op) {
        expect(fn () => $this->storage->store($op['key'], $op['value']))
            ->not->toThrow(Exception::class);
    }
});
