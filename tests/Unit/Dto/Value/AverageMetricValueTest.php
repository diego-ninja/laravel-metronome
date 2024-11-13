<?php

use Ninja\Metronome\Dto\Value\AverageMetricValue;

it('creates average metric value', function () {
    $value = new AverageMetricValue(10.0, 20.0, 2);

    expect($value->value())->toEqual(10.0)
        ->and($value->sum())->toEqual(20.0)
        ->and($value->count())->toBe(2)
        ->and($value->metadata())->toEqual([
            'sum' => 20.0,
            'count' => 2,
        ]);
});

it('creates empty average metric value', function () {
    $value = AverageMetricValue::empty();

    expect($value->value())->toEqual(0.0)
        ->and($value->sum())->toEqual(0.0)
        ->and($value->count())->toBe(1)
        ->and($value->metadata())->toEqual([
            'sum' => 0.0,
            'count' => 1,
        ]);
});

it('validates average calculation', function () {
    expect(fn () => new AverageMetricValue(11.0, 20.0, 2))
        ->toThrow(InvalidArgumentException::class, 'Average value must be sum/count');
});

it('validates count is positive', function () {
    expect(fn () => new AverageMetricValue(10.0, 20.0, 0))
        ->toThrow(InvalidArgumentException::class, 'Average count must be positive');
});

it('serializes to json', function () {
    $value = new AverageMetricValue(10.0, 20.0, 2);

    $expected = [
        'value' => 10.0,
        'metadata' => [
            'sum' => 20.0,
            'count' => 2,
        ],
    ];

    expect($value->array())->toEqual($expected)
        ->and(json_decode($value->serialize(), true))->toEqual($expected);
});

test('average value validation scenarios', function (float $value, float $sum, int $count, bool $shouldThrow) {
    if ($shouldThrow) {
        expect(fn () => new AverageMetricValue($value, $sum, $count))
            ->toThrow(InvalidArgumentException::class);
    } else {
        expect(fn () => new AverageMetricValue($value, $sum, $count))
            ->not->toThrow(InvalidArgumentException::class);
    }
})->with([
    [10.0, 20.0, 2, false],   // Valid: 20/2 = 10
    [5.0, 15.0, 3, false],    // Valid: 15/3 = 5
    [10.0, 20.0, 3, true],    // Invalid: 20/3 ≠ 10
    [10.0, 20.0, 0, true],    // Invalid: count = 0
    [0.0, 0.0, 1, false],     // Valid: empty case
]);
