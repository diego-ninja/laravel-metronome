<?php

use Ninja\Metronome\Dto\Dimension;
use Ninja\Metronome\Dto\DimensionCollection;

it('creates empty collection', function () {
    $collection = new DimensionCollection();

    expect($collection)->toBeEmpty();
});

it('creates collection from array', function () {
    $data = [
        ['name' => 'host', 'value' => 'localhost'],
        ['name' => 'env', 'value' => 'production']
    ];

    $collection = DimensionCollection::from($data);

    expect($collection)->toHaveCount(2)
        ->and($collection->first())->toBeInstanceOf(Dimension::class)
        ->and($collection->first()->name)->toBe('host');
});

it('creates collection from json', function () {
    $json = base64_encode(json_encode([
        ['name' => 'host', 'value' => 'localhost'],
        ['name' => 'env', 'value' => 'production']
    ]));

    $collection = DimensionCollection::from($json);

    expect($collection)->toHaveCount(2)
        ->and($collection->first())->toBeInstanceOf(Dimension::class)
        ->and($collection->first()->name)->toBe('host');
});

it('validates dimensions correctly', function () {
    $collection = DimensionCollection::from([
        ['name' => 'host', 'value' => 'localhost'],
        ['name' => 'env', 'value' => 'production']
    ]);

    expect($collection->valid(['host'], ['host', 'env', 'region']))->toBeTrue();
});

it('detects invalid dimensions', function () {
    $collection = DimensionCollection::from([
        ['name' => 'host', 'value' => 'localhost'],
        ['name' => 'invalid', 'value' => 'value']
    ]);

    expect($collection->valid(['host'], ['host', 'env']))->toBeFalse()
        ->and($collection->invalidDimensions(['host', 'env']))->toEqual(['invalid']);
});

it('detects missing required dimensions', function () {
    $collection = DimensionCollection::from([
        ['name' => 'env', 'value' => 'production']
    ]);

    expect($collection->valid(['host', 'env'], ['host', 'env']))->toBeFalse();
});

it('gets dimension names', function () {
    $collection = DimensionCollection::from([
        ['name' => 'host', 'value' => 'localhost'],
        ['name' => 'env', 'value' => 'production']
    ]);

    expect($collection->names())->toEqual(['host', 'env']);
});

it('serializes to json', function () {
    $collection = DimensionCollection::from([
        ['name' => 'host', 'value' => 'localhost']
    ]);

    $expectedJson = '[{"name":"host","value":"localhost"}]';

    expect($collection->json())->toBe($expectedJson);
});

it('serializes to string (base64)', function () {
    $collection = DimensionCollection::from([
        ['name' => 'host', 'value' => 'localhost']
    ]);

    $expectedJson = '[{"name":"host","value":"localhost"}]';
    $expectedString = base64_encode($expectedJson);

    expect((string)$collection)->toBe($expectedString);
});

test('validation scenarios', function (array $dimensions, array $required, array $allowed, bool $expected) {
    $collection = DimensionCollection::from($dimensions);
    expect($collection->valid($required, $allowed))->toBe($expected);
})->with([
    'all valid' => [
        [['name' => 'host', 'value' => 'localhost']],
        ['host'],
        ['host'],
        true
    ],
    'missing required' => [
        [['name' => 'env', 'value' => 'prod']],
        ['host'],
        ['host', 'env'],
        false
    ],
    'invalid dimension' => [
        [['name' => 'invalid', 'value' => 'value']],
        [],
        ['host', 'env'],
        false
    ],
    'empty collection with requirements' => [
        [],
        ['host'],
        ['host'],
        false
    ],
    'empty collection no requirements' => [
        [],
        [],
        [],
        true
    ],
]);
