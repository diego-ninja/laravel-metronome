<?php

namespace Tests\Unit\Mocks;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

class SwooleTableMock implements IteratorAggregate
{
    public const TYPE_STRING = 1;

    public const TYPE_INT = 2;

    protected array $data = [];

    protected array $columns = [];

    public function column($name, $type, $size = 0): static
    {
        $this->columns[$name] = ['type' => $type, 'size' => $size];

        return $this;
    }

    public function create(): true
    {
        return true;
    }

    public function get($key)
    {
        if (! isset($this->data[$key])) {
            return null;
        }

        $value = $this->data[$key];
        if (
            isset($value['expire_at']) &&
            $value['expire_at'] !== 0 &&
            now()->timestamp > $value['expire_at']
        ) {
            unset($this->data[$key]);

            return null;
        }

        return $value;
    }

    public function set($key, mixed $value): true
    {
        if (! isset($value['expire_at'])) {
            $value['expire_at'] = 0;
        }
        $this->data[$key] = $value;

        return true;
    }

    public function exists(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function exist(string $key): bool
    {
        return $this->exists($key);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    public function del($key): true
    {
        unset($this->data[$key]);

        return true;
    }

    public function getSize(): int
    {
        return 1000;
    }

    public function getMemorySize(): float|int
    {
        return 1024 * 1024;
    }
}
