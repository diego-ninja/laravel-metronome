<?php

use Carbon\Carbon;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Metrics\Storage\Contracts\StateStorage;
use Ninja\Metronome\StateManager;
use Ninja\Metronome\ValueObjects\TimeWindow;

beforeEach(function () {
    $this->stateStorage = Mockery::mock(StateStorage::class);
    $this->stateManager = new StateManager($this->stateStorage, 'test_prefix');
});

it('records successful window processing', function () {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);

    $this->stateStorage->shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function ($callback) {
            $pipe = Mockery::mock(StateStorage::class);
            $pipe->shouldReceive('set')->once();
            $pipe->shouldReceive('hset')->once();
            $callback($pipe);

            return [];
        });

    $this->stateManager->success($window);
});

it('checks if window was processed successfully', function () {
    $window = TimeWindow::forAggregation(Aggregation::Realtime);

    $this->stateStorage->shouldReceive('hExists')
        ->once()
        ->andReturn(true);

    expect($this->stateManager->wasSuccess($window))->toBeTrue();
});

it('increments error count', function () {
    $this->stateStorage->shouldReceive('increment')
        ->once()
        ->andReturn(1);

    $this->stateManager->error(Aggregation::Realtime);
});

it('gets last processing time', function () {
    $timestamp = time();

    $this->stateStorage->shouldReceive('get')
        ->once()
        ->andReturn($timestamp);

    $lastTime = $this->stateManager->last(Aggregation::Realtime);
    expect($lastTime)->toEqual(Carbon::createFromTimestamp($timestamp));
});

it('gets error count', function () {
    $this->stateStorage->shouldReceive('get')
        ->once()
        ->andReturn('5');

    expect($this->stateManager->errors(Aggregation::Realtime))->toBe(5);
});

it('resets error count', function () {
    $this->stateStorage->shouldReceive('delete')
        ->once();

    $this->stateManager->reset(Aggregation::Realtime);
});

it('cleans old window states', function () {
    $before = now()->subDay();

    $this->stateStorage->shouldReceive('hgetall')
        ->times(count(Aggregation::cases()))
        ->andReturn([
            'window1' => json_encode(['timestamp' => $before->subHour()->timestamp]),
            'window2' => json_encode(['timestamp' => time()]),
        ]);

    $this->stateStorage->shouldReceive('hdel')
        ->times(count(Aggregation::cases()));

    $this->stateManager->clean($before);
});

test('manager handles different aggregation windows', function (Aggregation $window) {
    $this->stateStorage->shouldReceive('increment')->once()->andReturn(1);
    $this->stateStorage->shouldReceive('get')->once()->andReturn('1');

    $this->stateManager->error($window);
    expect($this->stateManager->errors($window))->toBe(1);
})->with(fn () => Aggregation::cases());
