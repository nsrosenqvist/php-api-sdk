<?php

use NSRosenqvist\ApiToolkit\Structures\ListData;
use NSRosenqvist\ApiToolkit\Structures\ObjectData;

it('is countable', function () {
    $data = new ListData([
        'first' => 1,
        'second' => 1,
    ]);

    $this->assertEquals(2, count($data));
});

it('is iterable', function () {
    $data = new ListData([
        'first' => 1,
        'second' => 1,
    ]);

    $this->assertTrue((function () use ($data) {
        foreach ($data as $d) {
            return true;
        }
        return false;
    })());
});

it('supports array access', function () {
    $data = new ListData([
        $item = ['first' => 1],
    ]);

    $this->assertEquals(new ObjectData($item), $data[0]);
});

it('can be transformed into an array', function () {
    $data = new ListData($array = [
        ['first' => 1],
        ['second' => 2],
    ]);

    $this->assertEquals($array, $data->toArray());
});

it('can be transformed into JSON', function () {
    $data = new ListData($array = [
        ['first' => 1],
        ['second' => 2],
    ]);

    $this->assertEquals('[{"first":1},{"second":2}]', $data->toJson());
});
