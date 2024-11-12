<?php

use Ninja\Metronome\Provider\MetronomeServiceProvider;

uses()->group('unit')->in('Unit');
uses()->group('feature')->in('Feature');
uses()->group('integration')->in('Integration');

function defineEnvironment($app): void
{
    $app['config']->set('metronome.enabled', true);
    $app['config']->set('metronome.prefix', 'metronome_test');
    $app['config']->set('metronome.storage.metrics.driver', 'memory');
    $app['config']->set('metronome.storage.state.driver', 'memory');
}

function getPackageProviders($app): array
{
    return [
        MetronomeServiceProvider::class,
    ];
}
