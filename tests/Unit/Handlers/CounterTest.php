<?php

use Ninja\Metronome\Exceptions\InvalidMetricException;
use Ninja\Metronome\Metrics\Handlers\Counter;
use Ninja\Metronome\Dto\Value\CounterMetricValue;

it('computes sum of counter values', function () {
    $handler = new Counter();
    $values = [
        ['value' => 10.5, 'timestamp' => time()],
        ['value' => 5.5, 'timestamp' => time()],
    ];

    $result = $handler->compute($values);

    expect($result)->toBeInstanceOf(CounterMetricValue::class)
        ->and($result->value())->toEqual(16.0);
});

it('validates valid counter values', function () {
    $handler = new Counter();
    $values = [
        ['value' => 10.5, 'timestamp' => time()],
        ['value' => 5.5, 'timestamp' => time()],
    ];

    expect($handler->validate($values))->toBeTrue();
});

it('handles empty values', function () {
    $handler = new Counter();
    $result = $handler->compute([]);

    expect($result)->toBeInstanceOf(CounterMetricValue::class)
        ->and($result->value())->toEqual(0.0);
});

test('counter validation scenarios', function (array $values, bool $expected) {
    $handler = new Counter();
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
    $handler = new Counter();
    $values = [
        ['value' => -1.0, 'timestamp' => time()]
    ];

    expect(fn() => $handler->compute($values))
        ->toThrow(InvalidMetricException::class);
});
