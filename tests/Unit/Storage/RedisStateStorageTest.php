<?php

use Illuminate\Support\Facades\Redis;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Metrics\Storage\RedisStateStorage;

beforeEach(function () {
    $this->redis = Mockery::mock('Illuminate\Redis\Connections\Connection');
    Redis::shouldReceive('connection')
        ->with('metrics')
        ->andReturn($this->redis);

    $this->storage = new RedisStateStorage('test_prefix', 'metrics');
});

it('stores and retrieves simple values', function () {
    $this->redis->shouldReceive('set')
        ->with('test_prefix:state:test_key', 'test_value')
        ->once();

    $this->redis->shouldReceive('get')
        ->with('test_prefix:state:test_key')
        ->andReturn('test_value');

    $this->storage->set('test_key', 'test_value');
    expect($this->storage->get('test_key'))->toBe('test_value');
});

it('stores values with TTL', function () {
    $this->redis->shouldReceive('setex')
        ->with('test_prefix:state:test_key', 3600, 'test_value')
        ->once();

    $this->storage->set('test_key', 'test_value', 3600);
});

it('increments counter values', function () {
    $this->redis->shouldReceive('incr')
        ->with('test_prefix:state:test_counter')
        ->andReturn(1);

    expect($this->storage->increment('test_counter'))->toBe(1);
});

it('deletes values', function () {
    $this->redis->shouldReceive('del')
        ->with('test_prefix:state:test_key')
        ->once();

    $this->storage->delete('test_key');
});

it('handles hash operations', function () {
    $this->redis->shouldReceive('hset')
        ->with('test_prefix:state:test_hash', 'field1', 'value1')
        ->once();

    $this->redis->shouldReceive('hget')
        ->with('test_prefix:state:test_hash', 'field1')
        ->andReturn('value1');

    $this->redis->shouldReceive('hexists')
        ->with('test_prefix:state:test_hash', 'field1')
        ->andReturn(true);

    $this->redis->shouldReceive('hgetall')
        ->with('test_prefix:state:test_hash')
        ->andReturn(['field1' => 'value1', 'field2' => 'value2']);

    $this->redis->shouldReceive('hdel')
        ->with('test_prefix:state:test_hash', 'field1')
        ->once();

    $this->storage->hSet('test_hash', 'field1', 'value1');
    expect($this->storage->hGet('test_hash', 'field1'))->toBe('value1')
        ->and($this->storage->hExists('test_hash', 'field1'))->toBeTrue()
        ->and($this->storage->hGetAll('test_hash'))->toBe(['field1' => 'value1', 'field2' => 'value2']);

    $this->storage->hDel('test_hash', 'field1');
});

it('executes pipeline operations', function () {
    $this->redis->shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function ($callback) {
            $pipe = Mockery::mock('Illuminate\Redis\Connections\Connection');
            $pipe->shouldReceive('set')->once();
            $pipe->shouldReceive('incr')->once();
            $callback($pipe);

            return ['ok', 1];
        });

    $results = $this->storage->pipeline(function ($pipe) {
        $pipe->set('key1', 'value1');
        $pipe->incr('counter1');
    });

    expect($results)->toBeArray()->toHaveCount(2);
});

it('executes batch operations', function () {
    $operations = [
        ['set', ['key1', 'value1']],
        ['incr', ['counter1']],
    ];

    $this->redis->shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function ($callback) {
            $pipe = Mockery::mock('Illuminate\Redis\Connections\Connection');
            $pipe->shouldReceive('set')->once();
            $pipe->shouldReceive('incr')->once();
            $callback($pipe);

            return ['ok', 1];
        });

    $this->storage->batch($operations);
});

it('gets state for aggregation window', function () {
    $keys = [
        'test_prefix:state:window:realtime:key1',
        'test_prefix:state:window:realtime:key2',
    ];

    $this->redis->shouldReceive('keys')
        ->with('test_prefix:state:window:realtime:*')
        ->andReturn($keys);

    foreach ($keys as $key) {
        $this->redis->shouldReceive('get')
            ->with($key)
            ->once()
            ->andReturn($key === $keys[0] ? 'value1' : 'value2');
    }

    $state = $this->storage->state(Aggregation::Realtime);

    expect($state)
        ->toBeArray()
        ->toHaveCount(2)
        ->and($state)->toBe([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
});

it('reports health status', function () {
    $timestamp = time();

    $this->redis->shouldReceive('info')
        ->withNoArgs()
        ->andReturn([
            'used_memory_human' => '1.5M',
            'used_memory' => 1500000,
            'used_memory_peak' => 2000000,
            'mem_fragmentation_ratio' => 1.2,
            'connected_clients' => 10,
            'total_connections_received' => 2,
            'total_commands_processed' => 1000,
            'instantaneous_ops_per_sec' => 100,
            'rejected_connections' => 0,
            'expired_keys' => 0,
            'evicted_keys' => 0,
            'keyspace_hits' => 1000,
            'keyspace_misses' => 100,
            'rdb_last_save_time' => $timestamp,
        ]);

    $this->redis->shouldReceive('info')
        ->with('keyspace')
        ->andReturn(['db0' => ['keys' => 100]]);

    $health = $this->storage->health();

    expect($health)
        ->toHaveKey('status')
        ->and($health['status'])->toBe('healthy')
        ->and($health)->toHaveKeys([
            'used_memory',
            'connected_clients',
            'total_commands_processed',
            'total_connections_received',
            'instantaneous_ops_per_sec',
            'expired_keys',
            'evicted_keys',
            'keyspace_hits',
            'keyspace_misses',
            'memory_fragmentation_ratio',
        ]);
});

it('handles errors gracefully', function () {
    $this->redis->shouldReceive('get')
        ->andThrow(new Exception('Redis connection error'));

    expect(fn () => $this->storage->get('test_key'))
        ->toThrow(Exception::class, 'Redis connection error');
});

it('handles concurrent operations safely', function () {
    $this->redis->shouldReceive('set')
        ->twice()
        ->andReturn(true);

    $this->storage->set('key1', 'value1');
    $this->storage->set('key2', 'value2');
});

test('storage operations with different value types', function (string $value) {
    $this->redis->shouldReceive('set')
        ->with('test_prefix:state:test_key', $value)
        ->once();

    $this->redis->shouldReceive('get')
        ->with('test_prefix:state:test_key')
        ->andReturn($value);

    $this->storage->set('test_key', $value);
    expect($this->storage->get('test_key'))->toBe($value);
})->with([
    'simple string' => 'test',
    'json string' => '{"key":"value"}',
    'number string' => '123',
    'empty string' => '',
]);

test('storage handles null values correctly', function () {
    $this->redis->shouldReceive('get')
        ->andReturn(null);

    expect($this->storage->get('non_existent_key'))->toBeNull();
});
