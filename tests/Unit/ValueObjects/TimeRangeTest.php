<?php

use Carbon\Carbon;
use Ninja\Metronome\ValueObjects\TimeRange;

beforeEach(function () {
    Carbon::setTestNow('2024-01-01 10:00:00');
});

it('creates time range', function () {
    $from = Carbon::parse('2024-01-01 10:00:00');
    $to = Carbon::parse('2024-01-01 11:00:00');

    $range = new TimeRange($from, $to);

    expect($range->from)->toEqual($from)
        ->and($range->to)->toEqual($to)
        ->and($range->duration())->toBe(3600);
});

it('creates range for last period', function () {
    $range = TimeRange::last('1 hour');

    expect($range->from)->toEqual(Carbon::now()->subHour())
        ->and($range->to)->toEqual(Carbon::now());
});

it('creates range for today', function () {
    $range = TimeRange::today();

    expect($range->from)->toEqual(Carbon::now()->startOfDay())
        ->and($range->to)->toEqual(Carbon::now()->endOfDay());
});

it('creates range for week', function () {
    $range = TimeRange::week();

    expect($range->from)->toEqual(Carbon::now()->startOfWeek())
        ->and($range->to)->toEqual(Carbon::now()->endOfWeek());
});

it('creates range for month', function () {
    $range = TimeRange::month();

    expect($range->from)->toEqual(Carbon::now()->startOfMonth())
        ->and($range->to)->toEqual(Carbon::now()->endOfMonth());
});

it('checks if contains timestamp', function () {
    $range = TimeRange::last('1 hour');

    expect($range->contains(Carbon::now()->subMinutes(30)))->toBeTrue()
        ->and($range->contains(Carbon::now()->subHours(2)))->toBeFalse();
});

it('checks if overlaps another range', function () {
    $range1 = new TimeRange(
        Carbon::parse('2024-01-01 10:00:00'),
        Carbon::parse('2024-01-01 11:00:00')
    );

    $range2 = new TimeRange(
        Carbon::parse('2024-01-01 10:30:00'),
        Carbon::parse('2024-01-01 11:30:00')
    );

    $range3 = new TimeRange(
        Carbon::parse('2024-01-01 11:00:00'),
        Carbon::parse('2024-01-01 12:00:00')
    );

    expect($range1->overlaps($range2))->toBeTrue()
        ->and($range1->overlaps($range3))->toBeFalse();
});

it('throws exception when from is after to', function () {
    $from = Carbon::parse('2024-01-01 11:00:00');
    $to = Carbon::parse('2024-01-01 10:00:00');

    expect(fn() => new TimeRange($from, $to))->toThrow(InvalidArgumentException::class);
});

it('serializes to json', function () {
    $from = Carbon::parse('2024-01-01 10:00:00');
    $to = Carbon::parse('2024-01-01 11:00:00');
    $range = new TimeRange($from, $to);

    $expected = [
        'from' => $from->toDateTimeString(),
        'to' => $to->toDateTimeString(),
        'duration' => 3600
    ];

    expect($range->jsonSerialize())->toEqual($expected)
        ->and(json_decode($range->json(), true))->toEqual($expected);
});

it('converts to string', function () {
    $from = Carbon::parse('2024-01-01 10:00:00');
    $to = Carbon::parse('2024-01-01 11:00:00');
    $range = new TimeRange($from, $to);

    expect((string)$range)->toBe('2024-01-01 10:00:00 -> 2024-01-01 11:00:00');
});
