<?php

use Ninja\Metronome\Dto\Value\PercentageMetricValue;

it('creates percentage metric value', function () {
    $value = new PercentageMetricValue(75.0, 100.0, 10);

    expect($value->value())->toEqual(75.0)
        ->and($value->total())->toEqual(100.0)
        ->and($value->count())->toBe(10)
        ->and($value->percentage())->toEqual(75.0);
});

it('creates empty percentage metric value', function () {
    $value = PercentageMetricValue::empty();

    expect($value->value())->toEqual(0.0)
        ->and($value->total())->toEqual(0.0)
        ->and($value->count())->toBe(1)
        ->and($value->percentage())->toEqual(0.0);
});

it('throws exception for negative values', function () {
    expect(fn () => new PercentageMetricValue(-1, 100))
        ->toThrow(InvalidArgumentException::class, 'Percentage value must be non-negative');
});

it('throws exception for negative total', function () {
    expect(fn () => new PercentageMetricValue(50, -100))
        ->toThrow(InvalidArgumentException::class, 'Percentage total must be non-negative');
});

it('throws exception when value greater than total', function () {
    expect(fn () => new PercentageMetricValue(150, 100))
        ->toThrow(InvalidArgumentException::class, 'Percentage value cannot be greater than total');
});

it('throws exception for non-positive count', function () {
    expect(fn () => new PercentageMetricValue(50, 100, 0))
        ->toThrow(InvalidArgumentException::class, 'Percentage count must be positive');
});

it('calculates percentage correctly', function () {
    $value = new PercentageMetricValue(75.0, 100.0);
    expect($value->percentage())->toEqual(75.0);

    $value = new PercentageMetricValue(0.0, 100.0);
    expect($value->percentage())->toEqual(0.0);

    $value = new PercentageMetricValue(0.0, 0.0);
    expect($value->percentage())->toEqual(0.0);
});

it('serializes to json', function () {
    $value = new PercentageMetricValue(75.0, 100.0, 10);

    $expected = [
        'value' => 75.0,
        'metadata' => [
            'total' => 100.0,
            'count' => 10,
            'percentage' => 75.0,
        ],
    ];

    expect($value->array())->toEqual($expected)
        ->and(json_decode($value->serialize(), true))->toEqual($expected);
});

test('percentage validation scenarios', function (float $value, float $total, int $count, bool $shouldThrow) {
    if ($shouldThrow) {
        expect(fn () => new PercentageMetricValue($value, $total, $count))
            ->toThrow(InvalidArgumentException::class);
    } else {
        expect(fn () => new PercentageMetricValue($value, $total, $count))
            ->not->toThrow(InvalidArgumentException::class);
    }
})->with([
    [75.0, 100.0, 1, false],    // Valid case
    [0.0, 0.0, 1, false],       // Zero case
    [-1.0, 100.0, 1, true],     // Negative value
    [75.0, -100.0, 1, true],    // Negative total
    [150.0, 100.0, 1, true],    // Value > total
    [75.0, 100.0, 0, true],     // Invalid count
    [75.0, 100.0, -1, true],    // Negative count
]);
