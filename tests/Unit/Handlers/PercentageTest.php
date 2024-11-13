<?php

use Ninja\Metronome\Metrics\Handlers\Percentage;
use Ninja\Metronome\Dto\Value\PercentageMetricValue;
use Ninja\Metronome\Exceptions\InvalidMetricException;

beforeEach(function () {
    $this->handler = new Percentage();
});

it('computes percentage correctly', function () {
    $values = [
        ['value' => 75, 'timestamp' => time(), 'metadata' => ['total' => 100]],
        ['value' => 150, 'timestamp' => time(), 'metadata' => ['total' => 200]],
    ];

    $result = $this->handler->compute($values);

    expect($result)->toBeInstanceOf(PercentageMetricValue::class)
        ->and($result->value())->toBe(225.0)        // suma de valores
        ->and($result->total())->toBe(300.0)        // suma de totales
        ->and($result->percentage())->toBe(75.0)    // (225/300)*100
        ->and($result->count())->toBe(2);
});

it('handles empty values', function () {
    $result = $this->handler->compute([]);

    expect($result)->toBeInstanceOf(PercentageMetricValue::class)
        ->and($result->value())->toBe(0.0)
        ->and($result->total())->toBe(0.0)
        ->and($result->percentage())->toBe(0.0)
        ->and($result->count())->toBe(0);
});

it('handles zero total correctly', function () {
    $values = [
        ['value' => 0, 'timestamp' => time(), 'metadata' => ['total' => 0]]
    ];

    $result = $this->handler->compute($values);

    expect($result->percentage())->toBe(0.0);
});

it('throws exception for missing total', function () {
    $values = [
        ['value' => 75, 'timestamp' => time()]  // Sin total
    ];

    expect(fn() => $this->handler->compute($values))
        ->toThrow(InvalidMetricException::class, 'Percentage total must be provided');
});

it('throws exception for negative values', function () {
    $values = [
        ['value' => -75, 'timestamp' => time(), 'metadata' => ['total' => 100]]
    ];

    expect(fn() => $this->handler->compute($values))
        ->toThrow(InvalidMetricException::class, 'Percentage value must be non-negative');
});

it('throws exception for negative total', function () {
    $values = [
        ['value' => 75, 'timestamp' => time(), 'metadata' => ['total' => -100]]
    ];

    expect(fn() => $this->handler->compute($values))
        ->toThrow(InvalidMetricException::class, 'Percentage total must be non-negative');
});

it('throws exception when value exceeds total', function () {
    $values = [
        ['value' => 150, 'timestamp' => time(), 'metadata' => ['total' => 100]]
    ];

    expect(fn() => $this->handler->compute($values))
        ->toThrow(InvalidMetricException::class, 'Percentage value cannot be greater than total');
});

test('percentage validation scenarios', function (array $values, bool $expected) {
    expect($this->handler->validate($values))->toBe($expected);
})->with([
    'valid values' => [
        'values' => [
            ['value' => 75.0, 'timestamp' => time(), 'metadata' => ['total' => 100]],
            ['value' => 150.0, 'timestamp' => time(), 'metadata' => ['total' => 200]]
        ],
        'expected' => true
    ],
    'zero values valid' => [
        'values' => [['value' => 0.0, 'timestamp' => time(), 'metadata' => ['total' => 100]]],
        'expected' => true
    ],
    'negative value invalid' => [
        'values' => [['value' => -1.0, 'timestamp' => time(), 'metadata' => ['total' => 100]]],
        'expected' => false
    ],
    'missing value' => [
        'values' => [['timestamp' => time(), 'metadata' => ['total' => 100]]],
        'expected' => false
    ],
    'missing total' => [
        'values' => [['value' => 75.0, 'timestamp' => time()]],
        'expected' => false
    ],
    'value exceeds total' => [
        'values' => [['value' => 150.0, 'timestamp' => time(), 'metadata' => ['total' => 100]]],
        'expected' => false
    ]
]);

it('accumulates multiple values correctly', function () {
    $values = [
        ['value' => 30, 'timestamp' => time(), 'metadata' => ['total' => 100]], // 30%
        ['value' => 40, 'timestamp' => time(), 'metadata' => ['total' => 100]], // 40%
        ['value' => 60, 'timestamp' => time(), 'metadata' => ['total' => 200]]  // 30%
    ];

    $result = $this->handler->compute($values);

    expect($result->value())->toBe(130.0)       // 30 + 40 + 60
    ->and($result->total())->toBe(400.0)    // 100 + 100 + 200
    ->and($result->percentage())->toBe(32.5) // (130/400)*100
    ->and($result->count())->toBe(3);
});
