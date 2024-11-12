<?php

namespace Ninja\Metronome\Contracts;

interface MetricValue
{
    public function value(): float;
    public function metadata(): array;
    public function serialize(): string;
    public static function empty(): self;
}
