<?php

namespace Tests\Unit\Processors;

use Ninja\Metronome\Dto\Metadata;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Metrics\Storage\Contracts\MetricStorage;
use Ninja\Metronome\Processors\Contracts\Processable;
use Ninja\Metronome\Processors\Items\Type;
use Ninja\Metronome\Processors\MetricProcessor;
use Ninja\Metronome\Processors\TypeProcessor;
use Ninja\Metronome\ValueObjects\TimeWindow;

beforeEach(function () {
    $this->metricStorage = mock(MetricStorage::class);
    $this->metricProcessor = mock(MetricProcessor::class);
    $this->processor = new TypeProcessor($this->metricProcessor, $this->metricStorage);
});

it('processes metrics of given type', function () {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);
    $type = new Type(MetricType::Counter, $window);

    $pattern = sprintf(
        '%s:*:%s:%s:%d:*',
        config('metronome.prefix'),
        $type->type()->value,
        $window->aggregation->value,
        $window->slot
    );

    $keys = [
        'metronome:test:counter:realtime:1234567890:',
        'metronome:test2:counter:realtime:1234567890:',
    ];

    $this->metricStorage->expects('keys')
        ->with($pattern)
        ->andReturn($keys);

    $this->metricProcessor->expects('process')
        ->twice();

    $this->processor->process($type);

    expect($this->processor->keys()->count())->toBe(2);
});

it('skips processing when no keys found', function () {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);
    $type = new Type(MetricType::Counter, $window);

    $pattern = sprintf(
        '%s:*:%s:%s:%d:*',
        config('metronome.prefix'),
        $type->type()->value,
        $window->aggregation->value,
        $window->slot
    );

    $this->metricStorage->expects('keys')
        ->with($pattern)
        ->andReturn([]);

    $this->metricProcessor->expects('process')
        ->never();

    $this->processor->process($type);

    expect($this->processor->keys()->count())->toBe(0);
});

it('throws exception for invalid processable type', function () {
    $invalidItem = new class implements Processable
    {
        public function identifier(): string
        {
            return 'test';
        }

        public function metadata(): Metadata
        {
            return new Metadata([]);
        }
    };

    expect(fn () => $this->processor->process($invalidItem))
        ->toThrow(\InvalidArgumentException::class);
});

it('processes all metric types correctly', function (MetricType $type) {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);
    $typeItem = new Type($type, $window);

    $pattern = sprintf(
        '%s:*:%s:%s:%d:*',
        config('metronome.prefix'),
        $type->value,
        $window->aggregation->value,
        $window->slot
    );

    $key = "metronome:test:{$type->value}:realtime:{$window->slot}:";

    $this->metricStorage->expects('keys')
        ->with($pattern)
        ->andReturn([$key]);

    $this->metricProcessor->expects('process')
        ->once();

    $this->processor->process($typeItem);

    expect($this->processor->keys()->count())->toBe(1)
        ->and($this->processor->keys()->first())->toBe($key);
})->with(MetricType::all());
