<?php

use Ninja\Metronome\MetricAggregator;

if (! function_exists('counter')) {
    function counter(string $name, float $value = 1, ?array $dimensions = null): void
    {
        $aggregator = app(MetricAggregator::class);
        $aggregator->counter($name, $value, $dimensions);
    }
}

if (! function_exists('gauge')) {
    function gauge(string $name, float $value, ?array $dimensions = null): void
    {
        $aggregator = app(MetricAggregator::class);
        $aggregator->gauge($name, $value, $dimensions);
    }
}

if (! function_exists('histogram')) {
    function histogram(string $name, float $value, ?array $dimensions = null): void
    {
        $aggregator = app(MetricAggregator::class);
        $aggregator->histogram($name, $value, $dimensions);
    }
}

if (! function_exists('average')) {
    function average(string $name, float $value, ?array $dimensions = null): void
    {
        $aggregator = app(MetricAggregator::class);
        $aggregator->average($name, $value, $dimensions);
    }
}

if (! function_exists('rate')) {
    function rate(string $name, float $value = 1, array $dimensions = []): void
    {
        $aggregator = app(MetricAggregator::class);
        $aggregator->rate($name, $value, $dimensions);
    }
}

if (! function_exists('summary')) {
    function summary(string $name, float $value, array $dimensions = []): void
    {
        $aggregator = app(MetricAggregator::class);
        $aggregator->summary($name, $value, $dimensions);
    }
}

if (! function_exists('percentage')) {
    function percentage(string $name, float $value, float $total, array $dimensions = []): void
    {
        $aggregator = app(MetricAggregator::class);
        $aggregator->percentage($name, $value, $total, $dimensions);
    }
}
