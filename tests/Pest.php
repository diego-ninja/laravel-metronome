<?php

use Ninja\Metronome\Enums\Bucket;
use Ninja\Metronome\Enums\Quantile;
use Ninja\Metronome\Provider\MetronomeServiceProvider;

uses()->group('unit')->in('Unit');
uses()->group('feature')->in('Feature');
uses()->group('integration')->in('Integration');

expect()->extend('toBeCloseTo', function (float $expected, int $precision = 0) {
    $delta = 1 / (10 ** $precision);

    return abs($this->value - $expected) < $delta;
});
function defineEnvironment($app): void
{
    $app['config']->set('metronome.enabled', true);
    $app['config']->set('metronome.prefix', 'metronome_test');
    $app['config']->set('metronome.storage.metrics.driver', 'memory');
    $app['config']->set('metronome.storage.state.driver', 'memory');
    $app['config']->set('metronome.rate_interval', 3600);
    $app['config']->set('metronome.buckets', Bucket::Default->scale());
    $app['config']->set('metronome.quantiles', Quantile::scale());
}

function getPackageProviders($app): array
{
    return [
        MetronomeServiceProvider::class,
    ];
}
