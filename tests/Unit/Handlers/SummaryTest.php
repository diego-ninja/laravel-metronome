<?php

use Ninja\Metronome\Exceptions\InvalidMetricException;
use Ninja\Metronome\Metrics\Handlers\Summary;
use Ninja\Metronome\Dto\Value\SummaryMetricValue;

beforeEach(function () {
    $this->quantiles = [0.5, 0.9, 0.99];
    $this->handler = new Summary($this->quantiles);
});

it('computes summary metrics correctly', function () {
    $values = [
        ['value' => 10, 'timestamp' => time()],
        ['value' => 20, 'timestamp' => time()],
        ['value' => 30, 'timestamp' => time()],
        ['value' => 40, 'timestamp' => time()],
        ['value' => 50, 'timestamp' => time()],
    ];

    $result = $this->handler->compute($values);

    expect($result)->toBeInstanceOf(SummaryMetricValue::class)
        ->and($result->value())->toEqual(30.0) // mean value
        ->and($result->metadata())->toHaveKeys(['quantiles', 'count', 'sum'])
        ->and($result->metadata()['count'])->toBe(5)
        ->and($result->metadata()['sum'])->toBe(150.0);
});

it('calculates correct quantiles', function () {
    $values = [];
    for ($i = 1; $i <= 100; $i++) {
        $values[] = ['value' => $i, 'timestamp' => time()];
    }

    $result = $this->handler->compute($values);
    $quantiles = $result->metadata()['quantiles'];

    expect($quantiles)->toHaveKey('0.5')
        ->and($quantiles['0.5'])->toBeBetween(49, 51)
        ->and($quantiles['0.9'])->toBeBetween(89, 91)
        ->and($quantiles['0.99'])->toBeBetween(98, 100);
});

it('handles empty values', function () {
    $result = $this->handler->compute([]);

    expect($result)->toBeInstanceOf(SummaryMetricValue::class)
        ->and($result->value())->toEqual(0.0)
        ->and($result->metadata()['count'])->toBe(0)
        ->and($result->metadata()['sum'])->toBe(0.0)
        ->and($result->metadata()['quantiles'])->toHaveKeys(['0.5', '0.9', '0.99']);
});


it('validates quantiles are within range', function () {
    expect(fn() => new Summary([-0.1]))
        ->toThrow(InvalidMetricException::class)
        ->and(fn() => new Summary([1.1]))
        ->toThrow(InvalidMetricException::class);
});

it('validates quantiles are provided', function () {
    expect(fn() => new Summary([]))
        ->toThrow(InvalidMetricException::class, 'Summary must have quantiles defined');
});

it('throws exception for invalid values', function () {
    $values = [
        ['value' => 'not a number', 'timestamp' => time()]
    ];

    expect(fn() => $this->handler->compute($values))
        ->toThrow(InvalidMetricException::class);
});

test('summary validation scenarios', function (array $values, array $quantiles, bool $shouldThrow) {
    if ($shouldThrow) {
        expect(fn() => new Summary($quantiles))
            ->toThrow(InvalidMetricException::class, 'Quantiles must be between 0 and 1');
    } else {
        $handler = new Summary($quantiles);
        expect($handler->validate($values))->toBeTrue();
    }
})->with([
    'valid values and quantiles' => [
        'values' => [
            ['value' => 1.0, 'timestamp' => time()],
            ['value' => 2.0, 'timestamp' => time()]
        ],
        'quantiles' => [0.5, 0.9],
        'shouldThrow' => false
    ],
    'zero value valid' => [
        'values' => [['value' => 0.0, 'timestamp' => time()]],
        'quantiles' => [0.5],
        'shouldThrow' => false
    ],
    'invalid quantile range' => [
        'values' => [['value' => 1.0, 'timestamp' => time()]],
        'quantiles' => [1.5],
        'shouldThrow' => true
    ]
]);


it('handles single value correctly', function () {
    $values = [
        ['value' => 42, 'timestamp' => time()]
    ];

    $result = $this->handler->compute($values);

    expect($result->value())->toBe(42.0)
        ->and($result->metadata()['count'])->toBe(1)
        ->and($result->metadata()['sum'])->toBe(42.0)
        ->and((float)$result->metadata()['quantiles']['0.5'])->toBe(42.0);
});

it('maintains order of quantiles', function () {
    $values = array_map(fn($i) => [
        'value' => $i,
        'timestamp' => time()
    ], range(1, 100));

    $result = $this->handler->compute($values);
    $quantiles = $result->metadata()['quantiles'];

    expect(array_keys($quantiles))->toEqual(['0.5', '0.9', '0.99'])
        ->and($quantiles['0.5'])->toBeLessThan($quantiles['0.9'])
        ->and($quantiles['0.9'])->toBeLessThan($quantiles['0.99']);
});
