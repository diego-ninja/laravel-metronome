<?php

use Carbon\Carbon;
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
use Ninja\Metronome\Metrics\Storage\MemoryMetricStorage;

class TableMock implements IteratorAggregate
{
    public const TYPE_STRING = 1;

    public const TYPE_INT = 2;

    protected array $data = [];

    protected array $columns = [];

    public function column($name, $type, $size = 0)
    {
        $this->columns[$name] = ['type' => $type, 'size' => $size];

        return $this;
    }

    public function create()
    {
        return true;
    }

    public function get($key)
    {
        if (! isset($this->data[$key])) {
            return null;
        }

        $value = $this->data[$key];
        if (isset($value['expire_at']) && time() > $value['expire_at']) {
            unset($this->data[$key]);

            return null;
        }

        return $value;
    }

    public function set($key, array $value)
    {
        if (! isset($value['expire_at'])) {
            $value['expire_at'] = time() + 3600;
        }
        $this->data[$key] = $value;

        return true;
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    public function del($key)
    {
        unset($this->data[$key]);

        return true;
    }

    public function getSize()
    {
        return 1000;
    }

    public function getMemorySize()
    {
        return 1024 * 1024;
    }
}

// Mock de la clase Table original
class_alias(TableMock::class, 'Swoole\Table');

beforeEach(function () {
    $this->metricStorage = new MemoryMetricStorage('test_prefix', 1000);
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

    $this->metricStorage->store($key, $value);

    $storedValue = $this->metricStorage->value($key);
    expect($storedValue)
        ->toBeInstanceOf(CounterMetricValue::class)
        ->and($storedValue->value())->toBe(10.5);
});

it('accumulates counter values', function () {
    $key = new Key(
        name: 'test_metric',
        type: MetricType::Counter,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: time()
    );

    $this->metricStorage->store($key, new CounterMetricValue(10.0));
    $this->metricStorage->store($key, new CounterMetricValue(5.0));

    $storedValue = $this->metricStorage->value($key);
    expect($storedValue->value())->toBe(15.0);
});

it('stores and retrieves gauge metric value', function () {
    $now = Carbon::now();
    Carbon::setTestNow($now);

    $key = new Key(
        name: 'test_metric',
        type: MetricType::Gauge,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: time()
    );

    $value = new GaugeMetricValue(42.0);

    $this->metricStorage->store($key, $value);
    $retrieved = $this->metricStorage->value($key);

    expect($retrieved)
        ->toBeInstanceOf(GaugeMetricValue::class)
        ->and($retrieved->value())->toBe(42.0);
});

it('handles expired metrics', function () {
    $now = Carbon::create(2024, 1, 1, 10);
    Carbon::setTestNow($now);

    $key = new Key(
        name: 'test_metric',
        type: MetricType::Counter,
        window: Aggregation::Realtime,
        dimensions: new DimensionCollection,
        slot: $now->timestamp
    );

    $value = new CounterMetricValue(10.0);

    $this->metricStorage->store($key, $value);

    Carbon::setTestNow($now->copy()->addHours(2));

    $retrievedValue = $this->metricStorage->value($key);

    expect($retrievedValue)->toBeInstanceOf(CounterMetricValue::class)
        ->and($retrievedValue->value())->toBe(0.0);
});

it('returns storage health status', function () {
    $health = $this->metricStorage->health();

    expect($health)
        ->toHaveKey('status')
        ->and($health['status'])->toBe('healthy')
        ->and($health)->toHaveKey('metrics_count')
        ->and($health)->toHaveKey('memory')
        ->and($health['memory'])->toHaveKeys(['size', 'memory_size']);
});

test('each metric type is stored and retrieved correctly', function (MetricType $type) {
    if ($type === MetricType::Unknown) {
        $this->markTestSkipped('Unknown metric type is not implemented');
    }

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
        MetricType::Percentage => new PercentageMetricValue(1.0, 100.0),
        MetricType::Unknown => throw new \Exception('To be implemented') // Aseguramos que el total está presente
    };

    $this->metricStorage->store($key, $value);

    $storedData = $this->metricStorage->value($key);

    expect($storedData)
        ->toBeInstanceOf($value::class)
        ->and($storedData->value())->toBe(1.0);
})->with(fn () => array_filter(
    MetricType::cases(),
    fn ($type) => $type !== MetricType::Unknown
));
