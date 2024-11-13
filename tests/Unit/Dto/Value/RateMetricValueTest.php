<?php

use Ninja\Metronome\Dto\Value\RateMetricValue;

it('creates rate metric value', function () {
    $value = new RateMetricValue(10.5, 3600, 5);

    expect($value->value())->toEqual(10.5)
        ->and($value->metadata())->toEqual([
            'interval' => 3600,
            'count' => 5
        ]);
});

it('creates empty rate metric value', function () {
    $value = RateMetricValue::empty();

    expect($value->value())->toEqual(0.0)
        ->and($value->metadata()['interval'])->toBe(60)  // Default 1-minute interval
        ->and($value->metadata()['count'])->toBe(1);
});

it('throws exception for negative values', function () {
    expect(fn() => new RateMetricValue(-1, 3600))
        ->toThrow(InvalidArgumentException::class, 'Rate value must be non-negative');
});

it('throws exception for non-positive interval', function () {
    expect(fn() => new RateMetricValue(10, 0))
        ->toThrow(InvalidArgumentException::class, 'Rate interval must be positive');
});

it('serializes to json', function () {
    $value = new RateMetricValue(10.5, 3600, 5);

    $expected = [
        'value' => 10.5,
        'metadata' => [
            'interval' => 3600,
            'count' => 5
        ]
    ];

    expect($value->array())->toEqual($expected)
        ->and(json_decode($value->serialize(), true))->toEqual($expected);
});

test('rate validation scenarios', function (float $value, int $interval, int $count, bool $shouldThrow) {
    if ($shouldThrow) {
        expect(fn() => new RateMetricValue($value, $interval, $count))
            ->toThrow(InvalidArgumentException::class);
    } else {
        expect(fn() => new RateMetricValue($value, $interval, $count))
            ->not->toThrow(InvalidArgumentException::class);
    }
})->with([
    [10.5, 3600, 5, false],   // Valid case
    [0.0, 60, 1, false],      // Zero value
    [-1.0, 3600, 1, true],    // Negative value
    [10.5, 0, 1, true],       // Zero interval
    [10.5, -1, 1, true],      // Negative interval
]);
