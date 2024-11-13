<?php

use Ninja\Metronome\Dto\Value\HistogramMetricValue;

it('creates histogram metric value', function () {
    $buckets = [10, 20, 50, 100, 200, 500];
    $value = new HistogramMetricValue(15.5, $buckets, 1, 15.5);

    expect($value->value())->toEqual(15.5)
        ->and($value->metadata())->toEqual([
            'buckets' => $buckets,
            'count' => 1,
            'sum' => 15.5
        ]);
});

it('creates empty histogram metric value', function () {
    $value = HistogramMetricValue::empty();

    expect($value->value())->toEqual(0.0)
        ->and($value->metadata())->toEqual([
            'buckets' => [],
            'count' => 1,
            'sum' => 0.0
        ]);
});

it('throws exception for negative values', function () {
    expect(fn() => new HistogramMetricValue(-1, [10, 20]))
        ->toThrow(InvalidArgumentException::class, 'Histogram value must be non-negative');
});

it('throws exception for empty buckets', function () {
    expect(fn() => new HistogramMetricValue(10, []))
        ->toThrow(InvalidArgumentException::class, 'Histogram must have buckets defined');
});

it('initializes sum with value when not provided', function () {
    $value = new HistogramMetricValue(15.5, [10, 20, 50]);

    expect($value->metadata()['sum'])->toEqual(15.5);
});

it('uses provided sum when available', function () {
    $value = new HistogramMetricValue(15.5, [10, 20, 50], 1, 30.0);

    expect($value->metadata()['sum'])->toEqual(30.0);
});

it('serializes to json', function () {
    $buckets = [10, 20, 50];
    $value = new HistogramMetricValue(15.5, $buckets, 2, 31.0);

    $expected = [
        'value' => 15.5,
        'metadata' => [
            'buckets' => $buckets,
            'count' => 2,
            'sum' => 31.0
        ]
    ];

    expect($value->array())->toEqual($expected)
        ->and(json_decode($value->serialize(), true))->toEqual($expected);
});

test('histogram validation scenarios', function (float $value, array $buckets, ?int $count, ?float $sum, bool $shouldThrow) {
    if ($shouldThrow) {
        expect(fn() => new HistogramMetricValue($value, $buckets, $count, $sum))
            ->toThrow(InvalidArgumentException::class);
    } else {
        expect(fn() => new HistogramMetricValue($value, $buckets, $count, $sum))
            ->not->toThrow(InvalidArgumentException::class);
    }
})->with([
    [15.5, [10, 20, 50], 1, 15.5, false],       // Valid case
    [0.0, [10, 20], 1, 0.0, false],             // Zero value
    [-1.0, [10, 20], 1, -1.0, true],            // Negative value
    [15.5, [], 1, 15.5, true],                  // Empty buckets
    [15.5, [10, 20], null, null, false],        // Default count and sum
]);
