<?php

namespace Ninja\Metronome\Processors\Contracts;

use Ninja\DeviceTracker\DTO\Metadata;

interface Processable
{
    public function identifier(): string;

    public function metadata(): Metadata;
}
