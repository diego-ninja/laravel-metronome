<?php

namespace Ninja\Metronome\Processors\Items;

use Ninja\DeviceTracker\DTO\Metadata;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Processors\Contracts\Processable;
use Ninja\Metronome\ValueObjects\TimeWindow;

final readonly class Type implements Processable
{
    public function __construct(
        private MetricType $type,
        private TimeWindow $window
    ) {
    }
    public function identifier(): string
    {
        return sprintf(
            'metric_type:%s:%s:%d',
            $this->type->value,
            $this->window->aggregation->value,
            $this->window->slot
        );
    }

    public function metadata(): Metadata
    {
        return new Metadata([
            'type' => $this->type->value,
            'window' => $this->window->array()
        ]);
    }

    public function type(): MetricType
    {
        return $this->type;
    }

    public function window(): TimeWindow
    {
        return $this->window;
    }
}
