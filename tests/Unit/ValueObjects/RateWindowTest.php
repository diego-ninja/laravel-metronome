<?php

use Carbon\Carbon;
use Ninja\Metronome\ValueObjects\RateWindow;

beforeEach(function () {
    Carbon::setTestNow('2024-01-01 10:00:00');
});

it('creates from values', function () {
    $values = [
        ['value' => 1, 'timestamp' => 1704103200], // 2024-01-01 10:00:00
        ['value' => 2, 'timestamp' => 1704103800]  // 2024-01-01 10:10:00
    ];

    $window = RateWindow::fromValues($values, 3600);

    expect($window->start)->toBe(1704103200)
        ->and($window->end)->toBe(1704103800)
        ->and($window->values)->toBe($values)
        ->and($window->interval)->toBe(3600);
});

it('creates from timestamps', function () {
    $start = 1704103200; // 2024-01-01 10:00:00
    $end = 1704106800;   // 2024-01-01 11:00:00
    $metadata = ['test' => 'data'];

    $window = RateWindow::fromTimestamps($start, $end, 3600, $metadata);

    expect($window->start)->toBe($start)
        ->and($window->end)->toBe($end)
        ->and($window->values)->toBeEmpty()
        ->and($window->interval)->toBe(3600)
        ->and($window->metadata)->toBe($metadata);
});

it('calculates rate for empty window', function () {
    $window = RateWindow::fromTimestamps(1704103200, 1704106800, 3600);
    $result = $window->calculate([]);

    expect($result)->toEqual([
        'rate' => 0.0,
        'count' => 0,
        'interval' => 3600,
        'window_start' => 1704103200,
        'window_end' => 1704106800,
        'metadata' => []
    ]);
});

it('calculates rate for window with values', function () {
    $values = [
        ['value' => 1, 'timestamp' => 1704103200],
        ['value' => 2, 'timestamp' => 1704103800]
    ];

    $window = RateWindow::fromValues($values, 3600);
    $result = $window->calculate($values);

    // Rate = (count * interval) / duration = (2 * 3600) / 600 = 12
    expect($result['rate'])->toEqual(12.0)
        ->and($result['count'])->toBe(2)
        ->and($result['interval'])->toBe(3600);
});

it('merges windows', function () {
    $window1 = RateWindow::fromValues([
        ['value' => 1, 'timestamp' => 1704103200]
    ], 3600);

    $window2 = RateWindow::fromValues([
        ['value' => 2, 'timestamp' => 1704103800]
    ], 3600);

    $merged = $window1->merge($window2);

    expect($merged->start)->toBe(1704103200)
        ->and($merged->end)->toBe(1704103800)
        ->and($merged->values)->toHaveCount(2);
});

it('throws exception for invalid interval', function () {
    expect(fn() => RateWindow::fromTimestamps(1704103200, 1704106800, 0))
        ->toThrow(InvalidArgumentException::class);
});

it('throws exception when start is after end', function () {
    expect(fn() => RateWindow::fromTimestamps(1704106800, 1704103200, 3600))
        ->toThrow(InvalidArgumentException::class);
});

it('serializes to array and json', function () {
    $window = RateWindow::fromTimestamps(1704103200, 1704106800, 3600);

    $expected = [
        'start' => '2024-01-01 10:00:00',
        'end' => '2024-01-01 11:00:00',
        'duration' => 3600,
        'interval' => 3600,
        'value_count' => 0,
        'metadata' => []
    ];

    expect($window->array())->toEqual($expected)
        ->and(json_decode($window->json(), true))->toEqual($expected);
});

it('converts to string', function () {
    $window = RateWindow::fromTimestamps(1704103200, 1704106800, 3600);

    expect((string)$window)->toBe('Rate[3600s] 2024-01-01 10:00:00 -> 2024-01-01 11:00:00 (0 values)');
});
