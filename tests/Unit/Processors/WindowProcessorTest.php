<?php

namespace Tests\Unit\Processors;

use Carbon\Carbon;
use Ninja\Metronome\Dto\Metadata;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Metrics\Storage\Contracts\MetricStorage;
use Ninja\Metronome\Metrics\Storage\Contracts\StateStorage;
use Ninja\Metronome\Processors\Contracts\Processable;
use Ninja\Metronome\Processors\Items\Window;
use Ninja\Metronome\Processors\TypeProcessor;
use Ninja\Metronome\Processors\WindowProcessor;
use Ninja\Metronome\StateManager;
use Ninja\Metronome\ValueObjects\TimeWindow;

beforeEach(function () {
    $this->storage = mock(MetricStorage::class);
    $this->stateStorage = mock(StateStorage::class);
    $this->typeProcessor = mock(TypeProcessor::class);
    $this->stateManager = new StateManager($this->stateStorage, 'test');

    $this->processor = new WindowProcessor(
        $this->typeProcessor,
        $this->storage,
        $this->stateManager
    );
});

it('processes all metric types for a window', function () {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);
    $item = new Window($window);

    $this->typeProcessor->expects('process')
        ->times(count(MetricType::all()));

    $this->stateStorage->expects('pipeline')
        ->once()
        ->andReturn([]);

    $this->processor->process($item);
});

it('stores success state after processing', function () {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);
    $item = new Window($window);

    $this->stateStorage->allows('hSet')->andReturn(null);
    $this->stateStorage->allows('hExists')->andReturn(true);

    $this->typeProcessor->expects('process')
        ->times(count(MetricType::all()));

    $this->stateStorage->expects('pipeline')
        ->once()
        ->andReturn([]);

    $this->processor->process($item);

    expect($this->processor->state()->wasSuccess($window))->toBeTrue();
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

it('tracks state errors on processing failure', function () {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);
    $item = new Window($window);

    $this->typeProcessor->expects('process')
        ->andThrow(new \Exception('Processing failed'));

    $this->stateStorage->allows('increment')->andReturn(1);
    $this->stateStorage->allows('get')->andReturn('1');

    expect(fn () => $this->processor->process($item))
        ->toThrow(\Exception::class)
        ->and($this->processor->state()->errors($window->aggregation))->toBe(1);
});

it('finds pending windows correctly', function () {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);

    $key = sprintf(
        'metronome:test:counter:realtime:%d:base64dimensions',
        $window->slot
    );

    $this->storage->expects('keys')
        ->with($window->aggregation->pattern())
        ->andReturn([$key]);

    $this->stateStorage->expects('hExists')
        ->andReturn(false);


    Carbon::setTestNow(now()->add('1 hour'));
    $pending = $this->processor->pending($window->aggregation);

    expect($pending)->toHaveCount(1)
        ->and($pending->first())->toBeInstanceOf(TimeWindow::class)
        ->and($pending->first()->slot)->toBe($window->slot);
});

test('aggregation state retrieval', function () {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);

    $this->stateStorage->expects('get')
        ->andReturn((string) time());

    expect($this->processor->state()->last($window->aggregation))
        ->toBeInstanceOf(Carbon::class);
});
