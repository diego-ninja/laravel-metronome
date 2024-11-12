<?php

namespace Ninja\Metronome\Processors\Items;

use Ninja\DeviceTracker\DTO\Metadata;
use Ninja\Metronome\Dto\Key;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Processors\Contracts\Processable;
use Ninja\Metronome\ValueObjects\TimeWindow;

final readonly class Metric implements Processable
{
    public function __construct(
        private Key $key,
        private TimeWindow $window
    ) {
    }

    public function identifier(): string
    {
        return $this->key;
    }

    public function metadata(): Metadata
    {
        return new Metadata([
            'key' => (string) $this->key,
            'type' => $this->key->type->value,
            'window' => $this->window->array()
        ]);
    }

    public function key(): Key
    {
        return $this->key;
    }

    public function window(): TimeWindow
    {
        return $this->window;
    }
}
