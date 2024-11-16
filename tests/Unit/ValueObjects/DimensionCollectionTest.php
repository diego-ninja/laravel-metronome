<?php

use Ninja\Metronome\Dto\Dimension;
use Ninja\Metronome\Dto\DimensionCollection;

it('creates empty collection', function () {
    $collection = new DimensionCollection;

    expect($collection)->toBeEmpty();
});

it('creates collection from array', function () {
    $data = [
        ['host' => 'localhost'],
        ['env' => 'production'],
    ];

    $collection = DimensionCollection::from($data);

    expect($collection)->toHaveCount(2)
        ->and($collection->first())->toBeInstanceOf(Dimension::class)
        ->and($collection->first()->name)->toBe('host');
});

it('creates collection from json', function () {
    $json = base64_encode(json_encode([
        ['host' => 'localhost'],
        ['env' => 'production'],
    ]));

    $collection = DimensionCollection::from($json);

    expect($collection)->toHaveCount(2)
        ->and($collection->first())->toBeInstanceOf(Dimension::class)
        ->and($collection->first()->name)->toBe('host');
});

it('validates dimensions correctly', function () {
    $collection = DimensionCollection::from([
        ['host' => 'localhost'],
        ['env' => 'production'],
    ]);

    expect($collection->valid(['host'], ['host', 'env', 'region']))->toBeTrue();
});

it('detects invalid dimensions', function () {
    $collection = DimensionCollection::from([
        ['host' => 'localhost'],
        ['invalid' => 'value'],
    ]);

    expect($collection->valid(['host'], ['host', 'env']))->toBeFalse()
        ->and($collection->invalidDimensions(['host', 'env']))->toEqual(['invalid']);
});

it('detects missing required dimensions', function () {
    $collection = DimensionCollection::from([
        ['env' => 'production'],
    ]);

    expect($collection->valid(['host', 'env'], ['host', 'env']))->toBeFalse();
});

it('gets dimension names', function () {
    $collection = DimensionCollection::from([
        ['host' => 'localhost'],
        ['env' => 'production'],
    ]);

    expect($collection->names())->toEqual(['host', 'env']);
});

it('serializes to json', function () {
    $collection = DimensionCollection::from([
        ['host' => 'localhost'],
    ]);

    $expectedJson = '[{"host":"localhost"}]';

    expect($collection->json())->toBe($expectedJson);
});

it('serializes to string (base64)', function () {
    $collection = DimensionCollection::from([
        ['host' => 'localhost'],
    ]);

    $expectedJson = '[{"host":"localhost"}]';
    $expectedString = base64_encode($expectedJson);

    expect((string) $collection)->toBe($expectedString);
});

test('validation scenarios', function (array $dimensions, array $required, array $allowed, bool $expected) {
    $collection = DimensionCollection::from($dimensions);
    expect($collection->valid($required, $allowed))->toBe($expected);
})->with([
    'all valid' => [
        [['host' => 'localhost']],
        ['host'],
        ['host'],
        true,
    ],
    'missing required' => [
        [['env' => 'prod']],
        ['host'],
        ['host', 'env'],
        false,
    ],
    'invalid dimension' => [
        [['invalid' => 'value']],
        [],
        ['host', 'env'],
        false,
    ],
    'empty collection with requirements' => [
        [],
        ['host'],
        ['host'],
        false,
    ],
    'empty collection no requirements' => [
        [],
        [],
        [],
        true,
    ],
]);
