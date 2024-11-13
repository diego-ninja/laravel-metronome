<?php

use Carbon\Carbon;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\ValueObjects\TimeWindow;

beforeEach(function () {
    Carbon::setTestNow('2024-01-01 10:00:00');
});

it('creates window for aggregation', function () {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);

    expect($window->from->timestamp)->toBe(Carbon::now()->startOfMinute()->timestamp)
        ->and($window->to->timestamp)->toBe(Carbon::now()->startOfMinute()->addMinute()->timestamp)
        ->and($window->slot)->toBe(Carbon::now()->startOfMinute()->timestamp)
        ->and($window->aggregation)->toBe(Aggregation::Realtime);
});

it('creates window from slot', function () {
    $slot = 1704103200; // 2024-01-01 10:00:00

    $window = TimeWindow::fromSlot($slot, Aggregation::Hourly);

    expect($window->from->timestamp)->toBe($slot)
        ->and($window->to->timestamp)->toBe($slot + 3600)
        ->and($window->slot)->toBe($slot)
        ->and($window->aggregation)->toBe(Aggregation::Hourly);
});

it('gets previous window', function () {
    $window = TimeWindow::forAggregation(Aggregation::Hourly);
    $previous = $window->previous();

    expect($previous->aggregation)->toBe(Aggregation::Realtime)
        ->and($previous->from->timestamp)->toBe(Carbon::now()->startOfMinute()->timestamp);
});

it('gets next window', function () {
    $window = TimeWindow::forAggregation(Aggregation::Hourly);
    $next = $window->next();

    expect($next->aggregation)->toBe(Aggregation::Daily)
        ->and($next->from->timestamp)->toBe(Carbon::now()->startOfDay()->timestamp);
});

it('returns null for next window of yearly', function () {
    $window = TimeWindow::forAggregation(Aggregation::Yearly);

    expect($window->next())->toBeNull();
});

it('checks if contains timestamp', function () {
    $window = TimeWindow::forAggregation(Aggregation::Hourly);

    expect($window->contains(Carbon::now()))->toBeTrue()
        ->and($window->contains(Carbon::now()->subHours(2)))->toBeFalse();
});

it('checks if overlaps another window', function () {
    $window1 = TimeWindow::forAggregation(Aggregation::Hourly);
    $window2 = TimeWindow::fromSlot($window1->slot + 1800, Aggregation::Hourly); // 30 min overlap
    $window3 = TimeWindow::fromSlot($window1->slot + 3600, Aggregation::Hourly); // No overlap

    expect($window1->overlaps($window2))->toBeTrue()
        ->and($window1->overlaps($window3))->toBeFalse();
});

it('creates from array', function () {
    $data = [
        'from' => '2024-01-01 10:00:00',
        'to' => '2024-01-01 11:00:00',
        'slot' => 1704103200,
        'aggregation' => 'hourly'
    ];

    $window = TimeWindow::from($data);

    expect($window->from->timestamp)->toBe(Carbon::parse($data['from'])->timestamp)
        ->and($window->to->timestamp)->toBe(Carbon::parse($data['to'])->timestamp)
        ->and($window->slot)->toBe($data['slot'])
        ->and($window->aggregation)->toBe(Aggregation::Hourly);
});

it('generates key with prefix', function () {
    $window = TimeWindow::forAggregation(Aggregation::Hourly);
    $key = $window->key('prefix');

    expect($key)->toBe(sprintf('prefix:*:hourly:%d:*', $window->slot));
});

it('throws exception when from is after to', function () {
    $data = [
        'from' => '2024-01-01 11:00:00', // After to
        'to' => '2024-01-01 10:00:00',
        'slot' => 1704103200,
        'aggregation' => 'hourly'
    ];

    expect(fn() => TimeWindow::from($data))->toThrow(InvalidArgumentException::class);
});
