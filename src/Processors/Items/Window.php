<?php

namespace Ninja\Metronome\Processors\Items;

use Ninja\DeviceTracker\DTO\Metadata;
use Ninja\Metronome\Processors\Contracts\Processable;
use Ninja\Metronome\ValueObjects\TimeWindow;

final readonly class Window implements Processable
{
    public function __construct(
        private TimeWindow $window
    ) {
    }
    public function identifier(): string
    {
        return sprintf(
            'window:%s:%d',
            $this->window->aggregation->value,
            $this->window->slot
        );
    }

    public function metadata(): Metadata
    {
        return new Metadata([
            'window' => $this->window->array()
        ]);
    }

    public function window(): TimeWindow
    {
        return $this->window;
    }
}
