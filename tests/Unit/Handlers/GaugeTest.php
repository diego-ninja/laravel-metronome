<?php

use Ninja\Metronome\Exceptions\InvalidMetricException;
use Ninja\Metronome\Metrics\Handlers\Gauge;
use Ninja\Metronome\Dto\Value\GaugeMetricValue;

it('computes latest gauge value', function () {
    $handler = new Gauge();
    $now = time();
    $values = [
        ['value' => 10.5, 'timestamp' => $now - 60],
        ['value' => 5.5, 'timestamp' => $now],
        ['value' => 15.5, 'timestamp' => $now - 30],
    ];

    $result = $handler->compute($values);

    expect($result)->toBeInstanceOf(GaugeMetricValue::class)
        ->and($result->value())->toEqual(5.5)
        ->and($result->metadata()['timestamp'])->toBe($now);
});

it('validates valid gauge values', function () {
    $handler = new Gauge();
    $values = [
        ['value' => 10.5, 'timestamp' => time()],
        ['value' => 5.5, 'timestamp' => time()],
    ];

    expect($handler->validate($values))->toBeTrue();
});

it('handles empty values', function () {
    $handler = new Gauge();
    $result = $handler->compute([]);

    expect($result)->toBeInstanceOf(GaugeMetricValue::class)
        ->and($result->value())->toEqual(0.0)
        ->and($result->metadata())->toHaveKey('timestamp')
        ->and($result->metadata()['timestamp'])->toBeInt();
});

it('always uses most recent timestamp', function () {
    $handler = new Gauge();
    $now = time();
    $values = [
        ['value' => 10.5, 'timestamp' => $now - 60],
        ['value' => 5.5, 'timestamp' => $now],
    ];

    $result = $handler->compute($values);

    expect($result->metadata()['timestamp'])->toBe($now);
});

test('gauge validation scenarios', function (array $values, bool $expected) {
    $handler = new Gauge();
    expect($handler->validate($values))->toBe($expected);
})->with([
    'valid values' => [
        'values' => [
            ['value' => 1.0, 'timestamp' => time()],
            ['value' => 2.0, 'timestamp' => time()]
        ],
        'expected' => true
    ],
    'zero value valid' => [
        'values' => [['value' => 0.0, 'timestamp' => time()]],
        'expected' => true
    ],
    'negative value invalid' => [
        'values' => [['value' => -1.0, 'timestamp' => time()]],
        'expected' => false
    ],
    'missing value key' => [
        'values' => [['timestamp' => time()]],
        'expected' => false
    ],
    'non-numeric value' => [
        'values' => [['value' => 'string', 'timestamp' => time()]],
        'expected' => false
    ],
]);

it('throws exception when computing invalid values', function () {
    $handler = new Gauge();
    $values = [
        ['value' => -1.0, 'timestamp' => time()]
    ];

    expect(fn() => $handler->compute($values))
        ->toThrow(InvalidMetricException::class);
});
