<?php

use Ninja\Metronome\Dto\Value\SummaryMetricValue;

it('creates summary metric value', function () {
    $quantiles = [0.5, 0.9, 0.99];
    $value = new SummaryMetricValue(15.5, $quantiles, 10, 155.0);

    expect($value->value())->toEqual(15.5)
        ->and($value->metadata())->toEqual([
            'quantiles' => $quantiles,
            'count' => 10,
            'sum' => 155.0,
        ]);
});

it('creates empty summary metric value', function () {
    $value = SummaryMetricValue::empty();

    expect($value->value())->toEqual(0.0)
        ->and($value->metadata())->toEqual([
            'quantiles' => [0.5], // Esperamos el quantil por defecto
            'count' => 1,
            'sum' => 0.0,
        ]);
});

it('throws exception for empty quantiles', function () {
    expect(fn () => new SummaryMetricValue(10.0, []))
        ->toThrow(InvalidArgumentException::class, 'Summary must have quantiles defined');
});

it('initializes sum with value when not provided', function () {
    $value = new SummaryMetricValue(15.5, [0.5, 0.9]);

    expect($value->metadata()['sum'])->toEqual(15.5);
});

it('uses provided sum when available', function () {
    $value = new SummaryMetricValue(15.5, [0.5, 0.9], 10, 155.0);

    expect($value->metadata()['sum'])->toEqual(155.0);
});

it('serializes to json', function () {
    $quantiles = [0.5, 0.9, 0.99];
    $value = new SummaryMetricValue(15.5, $quantiles, 10, 155.0);

    $expected = [
        'value' => 15.5,
        'metadata' => [
            'quantiles' => $quantiles,
            'count' => 10,
            'sum' => 155.0,
        ],
    ];

    expect($value->array())->toEqual($expected)
        ->and(json_decode($value->serialize(), true))->toEqual($expected);
});

test('summary validation scenarios', function (float $value, array $quantiles, ?int $count, ?float $sum, bool $shouldThrow) {
    if ($shouldThrow) {
        expect(fn () => new SummaryMetricValue($value, $quantiles, $count, $sum))
            ->toThrow(InvalidArgumentException::class);
    } else {
        expect(fn () => new SummaryMetricValue($value, $quantiles, $count, $sum))
            ->not->toThrow(InvalidArgumentException::class);
    }
})->with([
    [15.5, [0.5, 0.9], 10, 155.0, false],    // Valid case
    [0.0, [0.5], 1, 0.0, false],             // Single quantile
    [15.5, [], 1, 15.5, true],               // Empty quantiles
]);
