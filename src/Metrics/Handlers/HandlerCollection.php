<?php

namespace Ninja\Metronome\Metrics\Handlers;

use Illuminate\Support\Collection;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Metrics\Handlers\Contracts\MetricHandler;

final readonly class HandlerCollection
{
    private Collection $handlers;

    public function __construct()
    {
        $this->handlers = collect();
    }

    public static function fromArray(array $handlers): self
    {
        $collection = new self();
        foreach ($handlers as $type => $handler) {
            $collection->add(
                MetricType::from($type),
                $handler
            );
        }
        return $collection;
    }

    public static function make(array $handlers = []): self
    {
        return self::fromArray($handlers);
    }
    public function add(MetricType $type, MetricHandler $handler): self
    {
        $this->handlers->put($type->value, $handler);
        return $this;
    }

    public function get(MetricType $type): ?MetricHandler
    {
        return $this->handlers->get($type->value);
    }

    public function has(MetricType $type): bool
    {
        return $this->handlers->has($type->value);
    }

    public function remove(MetricType $type): self
    {
        $this->handlers->forget($type->value);
        return $this;
    }
    public function types(): Collection
    {
        return $this->handlers->keys()->map(fn($key) => MetricType::from($key));
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->handlers->$method(...$arguments);
    }
}
