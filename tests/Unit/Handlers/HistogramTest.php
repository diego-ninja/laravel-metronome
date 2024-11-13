<?php

use Ninja\Metronome\Dto\Value\HistogramMetricValue;
use Ninja\Metronome\Exceptions\InvalidMetricException;
use Ninja\Metronome\Metrics\Handlers\Histogram;

beforeEach(function () {
    $this->buckets = [10, 50, 100, 500, 1000];
    $this->handler = new Histogram($this->buckets);
});

it('computes histogram metrics correctly', function () {
    $values = [
        ['value' => 25, 'timestamp' => time()],
        ['value' => 75, 'timestamp' => time()],
        ['value' => 150, 'timestamp' => time()],
    ];

    $result = $this->handler->compute($values);

    expect($result)->toBeInstanceOf(HistogramMetricValue::class)
        ->and(round($result->value(), 6))->toEqual(83.333333)
        ->and($result->metadata())->toHaveKeys(['buckets', 'count', 'sum'])
        ->and($result->metadata()['count'])->toBe(3)
        ->and($result->metadata()['sum'])->toBe(250.0);
});

it('categorizes values into correct buckets', function () {
    $values = [
        ['value' => 5, 'timestamp' => time()],   // bucket 10
        ['value' => 25, 'timestamp' => time()],  // bucket 50
        ['value' => 75, 'timestamp' => time()],  // bucket 100
        ['value' => 750, 'timestamp' => time()], // bucket 1000
    ];

    $result = $this->handler->compute($values);
    $buckets = $result->metadata()['buckets'];

    expect($buckets)->toBeArray();

    // Probemos bucket por bucket
    foreach ($buckets as $bucket) {
        match ($bucket['le']) {
            10 => expect($bucket['count'])->toBe(1),
            50 => expect($bucket['count'])->toBe(2),
            100 => expect($bucket['count'])->toBe(3),
            500 => expect($bucket['count'])->toBe(3),
            1000 => expect($bucket['count'])->toBe(4),
            default => null
        };
    }
});
it('handles empty values', function () {
    $result = $this->handler->compute([]);

    expect($result)->toBeInstanceOf(HistogramMetricValue::class)
        ->and($result->value())->toEqual(0.0)
        ->and($result->metadata()['count'])->toBe(0)
        ->and($result->metadata()['sum'])->toBe(0.0)
        ->and($result->metadata()['buckets'])->toBeArray();
});

it('throws exception for negative values', function () {
    $values = [
        ['value' => -5, 'timestamp' => time()],
    ];

    expect(fn () => $this->handler->compute($values))
        ->toThrow(InvalidMetricException::class);
});

it('validates buckets are provided', function () {
    expect(fn () => new Histogram([]))
        ->toThrow(InvalidMetricException::class, 'Histogram must have buckets defined');
});

it('validates values are numeric', function () {
    $values = [
        ['value' => 'not a number', 'timestamp' => time()],
    ];

    expect(fn () => $this->handler->compute($values))
        ->toThrow(InvalidMetricException::class);
});

test('histogram validation scenarios', function (array $values, array $buckets, bool $expected) {
    $handler = new Histogram($buckets);
    expect($handler->validate($values))->toBe($expected);
})->with([
    'valid values and buckets' => [
        'values' => [
            ['value' => 1.0, 'timestamp' => time()],
            ['value' => 2.0, 'timestamp' => time()],
        ],
        'buckets' => [1, 5, 10],
        'expected' => true,
    ],
    'zero value valid' => [
        'values' => [['value' => 0.0, 'timestamp' => time()]],
        'buckets' => [1, 5, 10],
        'expected' => true,
    ],
    'negative value invalid' => [
        'values' => [['value' => -1.0, 'timestamp' => time()]],
        'buckets' => [1, 5, 10],
        'expected' => false,
    ],
    'missing value key' => [
        'values' => [['timestamp' => time()]],
        'buckets' => [1, 5, 10],
        'expected' => false,
    ],
    'non-numeric value' => [
        'values' => [['value' => 'string', 'timestamp' => time()]],
        'buckets' => [1, 5, 10],
        'expected' => false,
    ],
]);

function getBucketCount(array $buckets, float $bucket): int
{
    foreach ($buckets as $b) {
        if (abs($b['le'] - $bucket) < 0.000001) {
            return $b['count'];
        }
    }

    return 0;
}
