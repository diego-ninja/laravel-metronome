<?php

namespace Ninja\Metronome\Metrics\Storage;

use Illuminate\Support\Facades\Log;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Metrics\Storage\Contracts\StateStorage;
use RuntimeException;
use Swoole\Table;
use Throwable;

final class MemoryStateStorage implements StateStorage
{
    private readonly Table $storage;

    private readonly Table $hashStorage;

    private readonly Table $counterStorage;

    private readonly string $prefix;

    private array $operations = [];

    private array $errors = [];

    private bool $pipelining = false;

    public function __construct(?string $prefix = null)
    {
        $this->storage = new Table(1000);
        $this->storage->column('value', Table::TYPE_STRING, 1024);
        $this->storage->column('expire_at', Table::TYPE_INT);
        $this->storage->create();

        $this->hashStorage = new Table(10000);
        $this->hashStorage->column('value', Table::TYPE_STRING, 1024);
        $this->hashStorage->column('timestamp', Table::TYPE_INT);
        $this->hashStorage->create();

        $this->counterStorage = new Table(1000);
        $this->counterStorage->column('value', Table::TYPE_INT);
        $this->counterStorage->create();

        $this->prefix = $prefix ?: config('metronome.prefix');
    }

    public function get(string $key): ?string
    {
        if ($this->pipelining) {
            $this->operations[] = ['get', [$key]];

            return null;
        }

        return $this->_get($key);
    }

    private function _get(string $key): ?string
    {
        $data = $this->counter($key) ? $this->counterStorage->get($this->prefix($key)) : $this->storage->get($this->prefix($key));

        if (! $data || ($data['expire_at'] > 0 && $data['expire_at'] < now()->timestamp)) {
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, string $value, ?int $ttl = null): void
    {
        if ($this->pipelining) {
            $this->operations[] = ['set', [$key, $value, $ttl]];

            return;
        }

        $this->_set($key, $value, $ttl);
    }

    private function _set(string $key, string $value, ?int $ttl = null): void
    {
        $expireAt = $ttl ? now()->timestamp + $ttl : 0;

        $this->storage->set($this->prefix($key), [
            'value' => $value,
            'expire_at' => $expireAt,
        ]);
    }

    public function increment(string $key): int
    {
        if ($this->pipelining) {
            $this->operations[] = ['increment', [$key]];

            return 0;
        }

        return $this->_increment($key);
    }

    private function _increment(string $key): int
    {
        $key = $this->prefix($key);

        $current = $this->counterStorage->get($key);
        $value = ($current ? (int) $current['value'] : 0) + 1;

        $this->counterStorage->set($key, ['value' => $value]);

        return $value;
    }

    public function delete(string $key): void
    {
        if ($this->pipelining) {
            $this->operations[] = ['delete', [$key]];

            return;
        }

        $this->_delete($key);
    }

    private function _delete(string $key): void
    {
        $key = $this->prefix($key);

        $this->storage->del($key);
        $this->hashStorage->del($key);
        $this->counterStorage->del($key);
    }

    public function pipeline(callable $callback): array
    {
        if ($this->pipelining) {
            throw new RuntimeException('Nested pipelines are not supported');
        }

        $this->pipelining = true;
        $this->operations = [];

        try {
            $callback($this);

            return $this->execute();
        } finally {
            $this->pipelining = false;
            $this->operations = [];
        }
    }

    public function batch(array $operations): void
    {
        $this->pipeline(function (StateStorage $storage) use ($operations) {
            foreach ($operations as $operation) {
                [$method, $args] = $operation;
                $storage->$method(...$args);
            }
        });
    }

    public function hSet(string $key, string $field, string $value): void
    {
        if ($this->pipelining) {
            $this->operations[] = ['hSet', [$key, $field, $value]];

            return;
        }

        $this->_hSet($key, $field, $value);
    }

    private function _hSet(string $key, string $field, string $value): void
    {
        $hashKey = $this->prefix(sprintf('%s:%s', $key, $field));
        $this->hashStorage->set($hashKey, [
            'value' => $value,
            'timestamp' => now()->timestamp,
        ]);
    }

    public function hGet(string $key, string $field): ?string
    {
        if ($this->pipelining) {
            $this->operations[] = ['hGet', [$key, $field]];

            return null;
        }

        return $this->_hGet($key, $field);
    }

    private function _hGet(string $key, string $field): ?string
    {
        $hashKey = $this->prefix(sprintf('%s:%s', $key, $field));
        $data = $this->hashStorage->get($hashKey);

        return $data ? $data['value'] : null;
    }

    public function hExists(string $key, string $field): bool
    {
        if ($this->pipelining) {
            $this->operations[] = ['hExists', [$key, $field]];

            return false;
        }

        return $this->_hExists($key, $field);
    }

    private function _hExists(string $key, string $field): bool
    {
        $hashKey = $this->prefix(sprintf('%s:%s', $key, $field));

        return $this->hashStorage->exist($hashKey);
    }

    public function hGetAll(string $key): array
    {
        if ($this->pipelining) {
            $this->operations[] = ['hGetAll', [$key]];

            return [];
        }

        return $this->_hGetAll($key);
    }

    private function _hGetAll(string $key): array
    {
        $result = [];
        $prefix = $this->prefix($key).':';

        foreach ($this->hashStorage as $hashKey => $data) {
            if (str_starts_with($hashKey, $prefix)) {
                $field = $this->strip($hashKey);
                $result[$field] = $data['value'];
            }
        }

        return $result;
    }

    public function hDel(string $key, string $field): void
    {
        if ($this->pipelining) {
            $this->operations[] = ['hDel', [$key, $field]];

            return;
        }

        $this->_hDel($key, $field);
    }

    private function _hDel(string $key, string $field): void
    {
        $hashKey = $this->prefix(sprintf('%s:%s', $key, $field));
        $this->hashStorage->del($hashKey);
    }

    public function clean(): int
    {
        $count = 0;
        $now = now()->timestamp;
        $keysToDelete = [];

        foreach ($this->storage as $key => $data) {
            if (isset($data['expire_at']) && $data['expire_at'] > 0 && $data['expire_at'] < $now) {
                $keysToDelete[] = $key;
                $count++;
            }
        }

        foreach ($keysToDelete as $key) {
            $this->storage->del($key);
        }

        return $count;
    }

    public function state(Aggregation $window): array
    {
        $pattern = $this->prefix(sprintf('window:%s:', $window->value));
        $result = [];

        foreach ($this->storage as $key => $data) {
            if (str_starts_with($key, $pattern)) {
                $windowKey = str_replace($pattern, '', $key);
                $result[$windowKey] = $data['value'];
            }
        }

        return $result;
    }

    public function health(): array
    {
        return [
            'status' => 'healthy',
            'metrics_count' => iterator_count($this->storage),
            'memory' => [
                'size' => $this->storage->getSize(),
                'memory_size' => $this->storage->getMemorySize(),
            ],
            'last_cleanup' => now()->toDateTimeString(),
        ];
    }

    /**
     * @throws Throwable
     */
    private function execute(): array
    {
        if (empty($this->operations)) {
            return [];
        }

        $results = [];
        $errors = [];

        try {
            foreach ($this->operations as $index => $operation) {
                [$method, $args] = $operation;
                $results[] = $this->operation($method, $args);
            }
        } catch (Throwable $e) {
            $errors[] = [
                'index' => $index,
                'operation' => $operation,
                'error' => $e->getMessage(),
            ];

            throw $e;
        } finally {
            $this->operations = [];
        }

        if (! empty($errors)) {
            $this->errors = array_merge($this->errors, $errors);
            Log::warning('Some pipeline operations failed', [
                'errors' => $errors,
                'total_operations' => count($this->errors),
            ]);
        }

        return $results;
    }

    private function operation(string $method, array $args): mixed
    {
        return match ($method) {
            'get' => $this->_get(...$args),
            'set' => $this->_set(...$args) ?? null,
            'increment' => $this->_increment(...$args),
            'delete' => $this->_delete(...$args) ?? null,
            'hSet' => $this->_hSet(...$args) ?? null,
            'hGet' => $this->_hGet(...$args),
            'hExists' => $this->_hExists(...$args),
            'hGetAll' => $this->_hGetAll(...$args),
            'hDel' => $this->_hDel(...$args) ?? null,
            default => null,
        };
    }

    private function start(): void
    {
        $this->pipelining = true;
        $this->operations = [];
        $this->errors = [];
    }

    private function finish(): void
    {
        $this->pipelining = false;
        $this->operations = [];
    }

    public function reset(): void
    {
        $this->pipelining = false;
        $this->operations = [];
        $this->errors = [];
    }

    private function prefix(string $key): string
    {
        if (str_starts_with($key, $this->prefix.':')) {
            return $key;
        }

        return sprintf('%s:state:%s', $this->prefix, $key);
    }

    private function strip(string $key): string
    {
        $prefix = sprintf('%s:state:', $this->prefix);
        if (str_starts_with($key, $prefix)) {
            $parts = explode(':', $key);

            return end($parts);
        }

        return $key;
    }

    private function counter(string $key): bool
    {
        return $this->counterStorage->exist($this->prefix($key));
    }
}
