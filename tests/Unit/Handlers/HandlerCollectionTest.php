<?php

use Ninja\Metronome\Metrics\Handlers\HandlerCollection;
use Ninja\Metronome\Metrics\Handlers\Counter;
use Ninja\Metronome\Metrics\Handlers\Gauge;
use Ninja\Metronome\Enums\MetricType;

beforeEach(function () {
    $this->collection = new HandlerCollection();
});

it('creates empty collection', function () {
    expect($this->collection->toArray())->toBeEmpty();
});

it('adds and retrieves handlers', function () {
    $handler = new Counter();

    $this->collection->add(MetricType::Counter, $handler);

    expect($this->collection->get(MetricType::Counter))->toBe($handler)
        ->and($this->collection->has(MetricType::Counter))->toBeTrue();
});

it('creates from array of handlers', function () {
    $handlers = [
        MetricType::Counter->value => new Counter(),
        MetricType::Gauge->value => new Gauge()
    ];

    $collection = HandlerCollection::fromArray($handlers);

    expect($collection->has(MetricType::Counter))->toBeTrue()
        ->and($collection->has(MetricType::Gauge))->toBeTrue()
        ->and($collection->count())->toBe(2);
});

it('removes handlers', function () {
    $this->collection->add(MetricType::Counter, new Counter());

    expect($this->collection->has(MetricType::Counter))->toBeTrue();

    $this->collection->remove(MetricType::Counter);

    expect($this->collection->has(MetricType::Counter))->toBeFalse();
});

it('lists available metric types', function () {
    $this->collection->add(MetricType::Counter, new Counter());
    $this->collection->add(MetricType::Gauge, new Gauge());

    $types = $this->collection->types();

    expect($types)->toHaveCount(2)
        ->and($types)->toContain(MetricType::Counter)
        ->and($types)->toContain(MetricType::Gauge);
});

it('makes collection from static method', function () {
    $collection = HandlerCollection::make([
        MetricType::Counter->value => new Counter()
    ]);

    expect($collection)->toBeInstanceOf(HandlerCollection::class)
        ->and($collection->has(MetricType::Counter))->toBeTrue();
});

test('collection immutability', function () {
    $handler1 = new Counter();
    $handler2 = new Counter();

    $this->collection->add(MetricType::Counter, $handler1);
    $this->collection->add(MetricType::Counter, $handler2);

    expect($this->collection->get(MetricType::Counter))->toBe($handler2);
});

it('returns null for non-existent handler', function () {
    expect($this->collection->get(MetricType::Rate))->toBeNull();
});

it('supports collection methods', function () {
    $this->collection->add(MetricType::Counter, new Counter());
    $this->collection->add(MetricType::Gauge, new Gauge());

    expect($this->collection->all())->toHaveCount(2)
        ->and($this->collection->isEmpty())->toBeFalse()
        ->and($this->collection->keys()->toArray())->toBe([
            MetricType::Counter->value,
            MetricType::Gauge->value
        ]);
});
