<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\MetricManager;
use Ninja\Metronome\Metrics\Storage\Contracts\MetricStorage;
use Ninja\Metronome\Metrics\Storage\Contracts\StateStorage;
use Ninja\Metronome\Processors\WindowProcessor;
use Ninja\Metronome\StateManager;

beforeEach(function () {
    $this->storage = mock(MetricStorage::class);
    $this->stateStorage = mock(StateStorage::class);
    $this->windowProcessor = mock(WindowProcessor::class);
    $this->stateManager = new StateManager($this->stateStorage, 'test');

    $this->manager = new MetricManager(
        $this->windowProcessor,
        $this->storage,
        $this->stateManager
    );
});

it('processes window', function () {
    $window = Aggregation::Realtime;

    $this->windowProcessor->expects('process')->once();
    $this->windowProcessor->allows('state')->andReturn($this->stateManager);
    $this->windowProcessor->allows('keys')->andReturn(collect());
    $this->stateStorage->allows('get')->once();

    $this->manager->process($window);
});

it('skips disabled window', function () {
    $window = Aggregation::Yearly;

    $this->windowProcessor->expects('process')
        ->never();

    $this->manager->process($window);
});

it('handles processing errors', function () {
    $window = Aggregation::Realtime;

    $this->windowProcessor->expects('process')
        ->andThrow(new \Exception('Processing failed'));

    $this->stateStorage->allows('increment')
        ->andReturn(1);

    expect(fn () => $this->manager->process($window))
        ->toThrow(\Exception::class);
});

it('prunes old metrics', function () {
    $window = Aggregation::Realtime;
    $before = now()->sub($window->retention());

    $this->storage->expects('prune')
        ->withArgs(function ($w, $b) use ($window, $before) {
            return $w === $window && $b->timestamp === $before->timestamp;
        })
        ->andReturn(5);

    expect($this->manager->prune($window))->toBe(5);
});

it('retrieves status information', function () {
    $this->storage->allows('count')
        ->andReturn(['total' => 100, 'by_type' => []]);

    $this->storage->allows('health')
        ->andReturn(['status' => 'healthy']);

    $this->stateStorage->allows('get')
        ->andReturn(null);

    $status = $this->manager->status();

    expect($status)
        ->toHaveKeys(['enabled_types', 'windows', 'metrics_count', 'system_health'])
        ->and($status['system_health'])->toHaveKey('storage');
});

it('tracks window state', function () {
    $window = Aggregation::Realtime;
    $timestamp = time();

    $this->stateStorage->expects('get')
        ->with($this->stateManager->key('last_processing', $window->value))
        ->andReturn((string) $timestamp);

    expect($this->manager->last($window))->toBeInstanceOf(Carbon::class);

    $this->stateStorage->expects('get')
        ->with($this->stateManager->key('processing_errors', $window->value))
        ->andReturn('5');

    expect($this->manager->errors($window))->toBe(5);
});

it('resets window state', function () {
    $window = Aggregation::Realtime;

    $this->stateStorage->expects('delete')
        ->once();

    $this->manager->reset($window);
});

it('handles cleanup of processed metrics', function () {
    $window = Aggregation::Realtime;
    $keys = collect(['key1', 'key2']);

    $this->windowProcessor->allows('process');
    $this->windowProcessor->allows('state')->andReturn($this->stateManager);
    $this->windowProcessor->allows('keys')->andReturn($keys);
    $this->stateStorage->allows('get');

    $this->storage->expects('delete')
        ->with($keys->all())
        ->once();

    $this->manager->process($window);
});

it('respects retention period when pruning', function () {
    $window = Aggregation::Daily;
    $expectedBefore = now()->sub($window->retention());

    Carbon::setTestNow();

    $this->storage->allows('prune')
        ->withArgs(function ($w, $b) use ($window, $expectedBefore) {
            return $w === $window && abs($b->timestamp - $expectedBefore->timestamp) <= 1;
        })
        ->andReturnUsing(function () {
            return 10;
        });

    expect($this->manager->prune($window))->toBe(10);
});
