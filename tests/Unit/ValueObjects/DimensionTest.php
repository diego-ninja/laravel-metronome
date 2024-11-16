<?php

use Ninja\Metronome\Dto\Dimension;

it('creates dimension from constructor', function () {
    $dimension = new Dimension('host', 'localhost');

    expect($dimension->name)->toBe('host')
        ->and($dimension->value)->toBe('localhost');
});

it('creates dimension from array', function () {
    $data = ['host' => 'localhost'];
    $dimension = Dimension::from($data);

    expect($dimension->name)->toBe('host')
        ->and($dimension->value)->toBe('localhost');
});

it('creates dimension from json', function () {
    $json = '{"host":"localhost"}';
    $dimension = Dimension::from($json);

    expect($dimension->name)->toBe('host')
        ->and($dimension->value)->toBe('localhost');
});

it('creates dimension from another dimension', function () {
    $original = new Dimension('host', 'localhost');
    $dimension = Dimension::from($original);

    expect($dimension->name)->toBe('host')
        ->and($dimension->value)->toBe('localhost')
        ->and($dimension)->not->toBe($original); // Diferentes instancias
});

it('converts to array', function () {
    $dimension = new Dimension('host', 'localhost');

    expect($dimension->array())->toEqual([
        'host' => 'localhost',
    ]);
});

it('converts to label', function () {
    $dimension = new Dimension('host', 'localhost');

    expect($dimension->asLabel())->toEqual(['host' => 'localhost']);
});

it('serializes to json', function () {
    $dimension = new Dimension('host', 'localhost');
    $expected = '{"host":"localhost"}';

    expect($dimension->json())->toBe($expected)
        ->and(json_encode($dimension))->toBe($expected);
});

test('dataset examples', function (string $name, string $value, array $expectedLabel) {
    $dimension = new Dimension($name, $value);
    expect($dimension->asLabel())->toEqual($expectedLabel);
})->with([
    ['host', 'localhost', ['host' => 'localhost']],
    ['env', 'production', ['env' => 'production']],
    ['region', 'eu-west-1', ['region' => 'eu-west-1']],
]);
