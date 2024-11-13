<?php

use Carbon\Carbon;
use Ninja\Metronome\Dto\Value\GaugeMetricValue;

beforeEach(function () {
    Carbon::setTestNow('2024-01-01 10:00:00');
});

it('creates gauge metric value', function () {
    $value = new GaugeMetricValue(10.5);

    expect($value->value())->toEqual(10.5)
        ->and($value->metadata())->toEqual(['timestamp' => time()]);
});

it('creates gauge metric with custom timestamp', function () {
    $timestamp = time() - 3600;
    $value = new GaugeMetricValue(10.5, $timestamp);

    expect($value->value())->toEqual(10.5)
        ->and($value->metadata())->toEqual(['timestamp' => $timestamp]);
});

it('creates empty gauge metric value', function () {
    $value = GaugeMetricValue::empty();

    expect($value->value())->toEqual(0.0)
        ->and($value->metadata())->toEqual(['timestamp' => time()]);
});

it('serializes to json', function () {
    $timestamp = time();
    $value = new GaugeMetricValue(10.5, $timestamp);

    $expected = [
        'value' => 10.5,
        'metadata' => ['timestamp' => $timestamp],
    ];

    expect($value->array())->toEqual($expected)
        ->and(json_decode($value->serialize(), true))->toEqual($expected);
});

it('throws exception for negative values', function () {
    expect(fn () => new GaugeMetricValue(-1))
        ->toThrow(InvalidArgumentException::class, 'Gauge value must be non-negative');
});

test('gauge values validation', function (float $value, bool $shouldThrow) {
    if ($shouldThrow) {
        expect(fn () => new GaugeMetricValue($value))
            ->toThrow(InvalidArgumentException::class);
    } else {
        expect(fn () => new GaugeMetricValue($value))
            ->not->toThrow(InvalidArgumentException::class);
    }
})->with([
    [0, false],
    [1, false],
    [0.5, false],
    [PHP_FLOAT_MAX, false],
    [-1, true],
    [-0.1, true],
]);
