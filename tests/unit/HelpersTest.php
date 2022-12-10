<?php

use function NSRosenqvist\ApiToolkit\array_map_recursive;
use function NSRosenqvist\ApiToolkit\is_assoc;
use function NSRosenqvist\ApiToolkit\to_object;

test('is_assoc can check for associative arrays', function () {
    $array = [1, 2, 3];
    $this->assertNotTrue(is_assoc($array));

    $array = ['one' => 1, 'two' => 2, 'three' => 3];
    $this->assertTrue(is_assoc($array));
});

test('array_map_recursive can iterate and map multidimensional arrays', function () {
    $mapped = array_map_recursive(function ($item) {
        return is_string($item) ? 'lorem' : $item;
    }, $array = [
        'first' => [
            'assoc' => [
                'foo' => 'bar',
            ],
            'regular' => [
                1, 2, 3,
            ],
        ],
    ]);
    $array['first']['assoc']['foo'] = 'lorem';

    $this->assertEquals($array, $mapped);
});

test('to_object can convert a multidimensional array to a stdClass', function () {
    $array = [
        'first' => [
            'assoc' => [
                'foo' => 'bar',
            ],
            'regular' => [
                1, 2, 3,
            ],
        ],
    ];
    $object = (object) [
        'first' => (object) [
            'assoc' => (object) [
                'foo' => 'bar',
            ],
            'regular' => [
                1, 2, 3,
            ],
        ],
    ];

    $this->assertEquals($object, to_object($array));
});
