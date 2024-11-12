<?php

namespace Ninja\Metronome\Metrics\Storage\Contracts;

use Carbon\Carbon;
use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Dto\Key;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\ValueObjects\TimeWindow;

interface MetricStorage
{
    public function store(Key $key, MetricValue $value): void;
    public function value(Key $key): MetricValue;
    public function keys(string $pattern): array;
    public function delete(TimeWindow|array $keys): void;
    public function expired(TimeWindow $window): bool;
    public function prune(Aggregation $window, Carbon $before): int;
    public function count(Aggregation $window): array;
    public function health(): array;
}