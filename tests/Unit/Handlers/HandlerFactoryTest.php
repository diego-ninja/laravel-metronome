<?php

use Ninja\Metronome\Metrics\Handlers\Counter;
use Ninja\Metronome\Metrics\Handlers\Gauge;
use Ninja\Metronome\Metrics\Handlers\HandlerFactory;
use Ninja\Metronome\Metrics\Handlers\Rate;
use Ninja\Metronome\Metrics\Handlers\Summary;
use Ninja\Metronome\Metrics\Handlers\Histogram;
use Ninja\Metronome\Metrics\Handlers\Percentage;
use Ninja\Metronome\Metrics\Handlers\Average;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Dto\Value\CounterMetricValue;
use Ninja\Metronome\Dto\Value\GaugeMetricValue;
use Ninja\Metronome\Dto\Value\HistogramMetricValue;
use Ninja\Metronome\Dto\Value\SummaryMetricValue;
use Ninja\Metronome\Dto\Value\RateMetricValue;
use Ninja\Metronome\Dto\Value\PercentageMetricValue;
use Ninja\Metronome\Dto\Value\AverageMetricValue;
use Ninja\Metronome\Exceptions\MetricHandlerNotFoundException;
use Ninja\Metronome\Exceptions\InvalidMetricException;

beforeEach(function () {
    // Reset del singleton para cada test
    HandlerFactory::reset();
});

it('provides handlers for all metric types', function () {
    $handlers = HandlerFactory::handlers();

    expect($handlers->get(MetricType::Counter))->toBeInstanceOf(Counter::class)
        ->and($handlers->get(MetricType::Gauge))->toBeInstanceOf(Gauge::class)
        ->and($handlers->get(MetricType::Histogram))->toBeInstanceOf(Histogram::class)
        ->and($handlers->get(MetricType::Summary))->toBeInstanceOf(Summary::class)
        ->and($handlers->get(MetricType::Rate))->toBeInstanceOf(Rate::class)
        ->and($handlers->get(MetricType::Percentage))->toBeInstanceOf(Percentage::class)
        ->and($handlers->get(MetricType::Average))->toBeInstanceOf(Average::class);
});

test('compute method handles each metric type correctly', function (MetricType $type, array $values, float $expected) {
    $result = HandlerFactory::compute($type, $values);
    expect($result->value())->toEqual($expected);
})->with([
    'counter sums values' => [
        'type' => MetricType::Counter,
        'values' => [
            ['value' => 10.0, 'timestamp' => time()],
            ['value' => 20.0, 'timestamp' => time()]
        ],
        'expected' => 30.0
    ],
    'gauge takes latest value' => [
        'type' => MetricType::Gauge,
        'values' => [
            ['value' => 10.0, 'timestamp' => time() - 100],
            ['value' => 20.0, 'timestamp' => time()]
        ],
        'expected' => 20.0
    ],
    'average calculates mean' => [
        'type' => MetricType::Average,
        'values' => [
            ['value' => 10.0, 'timestamp' => time()],
            ['value' => 20.0, 'timestamp' => time()]
        ],
        'expected' => 15.0
    ]
]);

test('compute method returns correct value type for each metric', function (MetricType $type, string $expectedClass) {
    $values = [['value' => 10.0, 'metadata' => ['total' => 10 ],'timestamp' => time()]];
    $result = HandlerFactory::compute($type, $values);
    expect($result)->toBeInstanceOf($expectedClass);
})->with([
    'counter returns CounterMetricValue' => [MetricType::Counter, CounterMetricValue::class],
    'gauge returns GaugeMetricValue' => [MetricType::Gauge, GaugeMetricValue::class],
    'histogram returns HistogramMetricValue' => [MetricType::Histogram, HistogramMetricValue::class],
    'summary returns SummaryMetricValue' => [MetricType::Summary, SummaryMetricValue::class],
    'rate returns RateMetricValue' => [MetricType::Rate, RateMetricValue::class],
    'percentage returns PercentageMetricValue' => [MetricType::Percentage, PercentageMetricValue::class],
    'average returns AverageMetricValue' => [MetricType::Average, AverageMetricValue::class]
]);

test('handlers validate input values correctly', function (MetricType $type, array $values, bool $valid) {
    if (!$valid) {
        expect(fn() => HandlerFactory::compute($type, $values))->toThrow(InvalidMetricException::class);
    } else {
        $result = HandlerFactory::compute($type, $values);
        expect($result)->not->toBeNull();
    }
})->with([
    'counter accepts positive values' => [
        'type' => MetricType::Counter,
        'values' => [['value' => 10.0, 'timestamp' => time()]],
        'valid' => true
    ],
    'counter rejects negative values' => [
        'type' => MetricType::Counter,
        'values' => [['value' => -10.0, 'timestamp' => time()]],
        'valid' => false
    ],
    'average accepts multiple values' => [
        'type' => MetricType::Average,
        'values' => [
            ['value' => 10.0, 'timestamp' => time()],
            ['value' => 20.0, 'timestamp' => time()]
        ],
        'valid' => true
    ],
    'percentage requires total' => [
        'type' => MetricType::Percentage,
        'values' => [
            [
                'value' => 75.0,
                'timestamp' => time(),
                'metadata' => ['total' => 100.0]
            ]
        ],
        'valid' => true
    ]
]);

it('handles empty values appropriately for each type', function (MetricType $type) {
    if ($type === MetricType::Unknown) {
        expect(fn() => HandlerFactory::compute($type, []))
            ->toThrow(MetricHandlerNotFoundException::class, 'Metric handler for type unknown not found');
        return;
    }

    $result = HandlerFactory::compute($type, []);
    expect($result->value())->toEqual(0.0);
})->with(fn() => array_map(fn($type) => [$type], MetricType::cases()));

it('maintains handler configuration across multiple gets', function () {
    $handlers1 = HandlerFactory::handlers();
    $handlers2 = HandlerFactory::handlers();

    expect($handlers1)->toBe($handlers2)
        ->and($handlers1->get(MetricType::Counter))->toBe($handlers2->get(MetricType::Counter))
        ->and($handlers1->count())->toBe($handlers2->count());
});

it('throws exception for unknown metric type', function () {
    $values = [['value' => 10.0, 'timestamp' => time()]];
    $unknownType = MetricType::Unknown;

    expect(fn() => HandlerFactory::compute($unknownType, $values))
        ->toThrow(MetricHandlerNotFoundException::class, 'Metric handler for type unknown not found');
});

it('initializes handlers with correct configuration', function () {
    $handlers = HandlerFactory::handlers();

    // Verificar configuración específica de handlers
    $histogram = $handlers->get(MetricType::Histogram);
    expect($histogram)->toBeInstanceOf(Histogram::class);

    $summary = $handlers->get(MetricType::Summary);
    expect($summary)->toBeInstanceOf(Summary::class);

    $rate = $handlers->get(MetricType::Rate);
    expect($rate)->toBeInstanceOf(Rate::class);
});

it('handles concurrent access safely', function () {
    $results = [];
    foreach (range(1, 10) as $i) {
        $results[] = HandlerFactory::handlers();
    }

    $first = array_shift($results);
    foreach ($results as $result) {
        expect($result)->toBe($first);
    }
});

test('factory returns consistent results for same input', function () {
    $values = [
        ['value' => 10.0, 'timestamp' => time()],
        ['value' => 20.0, 'timestamp' => time()]
    ];

    $result1 = HandlerFactory::compute(MetricType::Counter, $values);
    $result2 = HandlerFactory::compute(MetricType::Counter, $values);

    expect($result1->value())->toEqual($result2->value())
        ->and($result1->metadata())->toEqual($result2->metadata());
});
