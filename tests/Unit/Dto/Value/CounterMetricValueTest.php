<?php

use Ninja\Metronome\Dto\Value\CounterMetricValue;

it('creates counter metric value', function () {
    $value = new CounterMetricValue(10.5);

    expect($value->value())->toEqual(10.5)
        ->and($value->metadata())->toBeArray()
        ->and($value->metadata())->toBeEmpty();
});

it('creates empty counter metric value', function () {
    $value = CounterMetricValue::empty();

    expect($value->value())->toEqual(0.0)
        ->and($value->metadata())->toBeArray()
        ->and($value->metadata())->toBeEmpty();
});

it('serializes to json', function () {
    $value = new CounterMetricValue(10.5);

    $expected = [
        'value' => 10.5,
        'metadata' => []
    ];

    expect($value->array())->toEqual($expected)
        ->and(json_decode($value->serialize(), true))->toEqual($expected);
});

it('throws exception for negative values', function () {
    expect(fn() => new CounterMetricValue(-1))
        ->toThrow(InvalidArgumentException::class, 'Counter value must be non-negative');
});

test('counter values validation', function (float $value, bool $shouldThrow) {
    if ($shouldThrow) {
        expect(fn() => new CounterMetricValue($value))
            ->toThrow(InvalidArgumentException::class);
    } else {
        expect(fn() => new CounterMetricValue($value))
            ->not->toThrow(InvalidArgumentException::class);
    }
})->with([
    [0, false],
    [1, false],
    [0.5, false],
    [PHP_FLOAT_MAX, false],
    [-1, true],
    [-0.1, true]
]);
