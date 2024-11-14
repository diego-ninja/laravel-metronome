<?php

use Carbon\Carbon;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Metrics\Storage\MemoryStateStorage;

beforeEach(function () {
    $this->prefix = 'test_prefix';
    $this->storage = new MemoryStateStorage($this->prefix);
});

it('stores and retrieves simple value', function () {
    $this->storage->set('test_key', 'test_value');
    expect($this->storage->get('test_key'))->toBe('test_value');
});

it('stores value with TTL', function () {
    Carbon::setTestNow('2024-01-01 10:00:00');
    $this->storage->set('test_key', 'test_value', 3600);
    expect($this->storage->get('test_key'))->toBe('test_value');

    Carbon::setTestNow('2024-01-01 11:10:00');
    expect($this->storage->get('test_key'))->toBeNull();
});

it('increments counter values', function () {
    expect($this->storage->increment('test_counter'))->toBe(1)
        ->and($this->storage->increment('test_counter'))->toBe(2)
        ->and($this->storage->increment('test_counter'))->toBe(3);
});

it('deletes values', function () {
    $this->storage->set('test_key', 'test_value');
    expect($this->storage->get('test_key'))->toBe('test_value');

    $this->storage->delete('test_key');
    expect($this->storage->get('test_key'))->toBeNull();
});

it('handles hash operations', function () {
    // Set and get hash value
    $this->storage->hSet('test_hash', 'field1', 'value1');
    expect($this->storage->hGet('test_hash', 'field1'))->toBe('value1')
        ->and($this->storage->hExists('test_hash', 'field1'))->toBeTrue()
        ->and($this->storage->hExists('test_hash', 'non_existent'))->toBeFalse();

    // Check existence

    // Get all hash values
    $this->storage->hSet('test_hash', 'field2', 'value2');
    expect($this->storage->hGetAll('test_hash'))->toBe([
        'field1' => 'value1',
        'field2' => 'value2',
    ]);

    // Delete hash field
    $this->storage->hDel('test_hash', 'field1');
    expect($this->storage->hExists('test_hash', 'field1'))->toBeFalse();
});

it('executes pipeline operations', function () {
    $results = $this->storage->pipeline(function ($pipe) {
        $pipe->set('key1', 'value1');
        $pipe->increment('counter1');
        $pipe->hSet('hash1', 'field1', 'value1');
    });

    expect($results)->toBeArray()
        ->and($this->storage->get('key1'))->toBe('value1')
        ->and($this->storage->hGet('hash1', 'field1'))->toBe('value1')
        ->and($this->storage->get('counter1'))->toBe('1');
});

it('executes batch operations', function () {
    $operations = [
        ['set', ['key1', 'value1']],
        ['increment', ['counter1']],
        ['hSet', ['hash1', 'field1', 'value1']],
    ];

    $this->storage->batch($operations);

    expect($this->storage->get('key1'))->toBe('value1')
        ->and($this->storage->get('counter1'))->toBe('1')
        ->and($this->storage->hGet('hash1', 'field1'))->toBe('value1');
});

it('gets state for aggregation window', function () {
    $window = Aggregation::Realtime;

    $this->storage->set(
        sprintf('window:%s:key1', $window->value),
        'value1'
    );
    $this->storage->set(
        sprintf('window:%s:key2', $window->value),
        'value2'
    );

    $state = $this->storage->state($window);
    expect($state)->toBe([
        'key1' => 'value1',
        'key2' => 'value2',
    ]);
});

it('cleans expired values', function () {
    Carbon::setTestNow('2024-01-01 10:00:00');

    // Set values with different TTLs
    $this->storage->set('key1', 'value1', 3600);
    $this->storage->set('key2', 'value2', 7200);

    Carbon::setTestNow('2024-01-01 11:30:00'); // After first TTL

    $cleanedCount = $this->storage->clean();
    expect($cleanedCount)->toBe(1)
        ->and($this->storage->get('key1'))->toBeNull()
        ->and($this->storage->get('key2'))->toBe('value2');
});

it('reports health status', function () {
    $health = $this->storage->health();

    expect($health)
        ->toHaveKey('status')
        ->and($health['status'])->toBe('healthy')
        ->and($health)->toHaveKeys(['metrics_count', 'memory']);
});

it('handles concurrent operations', function () {
    $this->storage->pipeline(function ($pipe) {
        $pipe->increment('counter');
        $pipe->hSet('hash', 'field', 'value');
    });

    $this->storage->pipeline(function ($pipe) {
        $pipe->increment('counter');
        $pipe->hSet('hash', 'field2', 'value2');
    });

    expect($this->storage->get('counter'))->toBe('2')
        ->and($this->storage->hGetAll('hash'))->toHaveCount(2);
});

test('storage handles different value types', function (string $value) {
    $this->storage->set('test_key', $value);
    expect($this->storage->get('test_key'))->toBe($value);
})->with([
    'simple string' => 'test',
    'json string' => '{"key":"value"}',
    'number string' => '123',
    'empty string' => '',
]);
