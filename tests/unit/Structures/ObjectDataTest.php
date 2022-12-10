<?php

use NSRosenqvist\ApiToolkit\Structures\ObjectData;

it('supports array access', function () {
    $data = new ObjectData([
        'first' => 1,
    ]);

    $this->assertEquals(1, $data['first']);
});

it('supports property access', function () {
    $data = new ObjectData([
        'first' => 1,
    ]);

    $this->assertEquals(1, $data->first);
});

it('supports array dot notation', function () {
    $data = new ObjectData([
        'first' => [
            'second' => 2,
        ],
    ]);

    $this->assertEquals(2, $data['first.second']);
});

it('can be transformed into an array', function () {
    $data = new ObjectData($array = [
        'first' => 1,
        'second' => 2,
    ]);

    $this->assertEquals($array, $data->toArray());
});

it('can be transformed into JSON', function () {
    $data = new ObjectData($array = [
        'first' => 1,
        'second' => 2,
    ]);

    $this->assertEquals('{"first":1,"second":2}', $data->toJson());
});

it('can have a property set', function () {
    $data = new ObjectData([
        'first' => 0,
    ]);

    $data->set('first', 1);
    $this->assertEquals(1, $data->get('first', 1));
});

it('can return a property', function () {
    $data = new ObjectData([
        'first' => 1,
    ]);

    $this->assertEquals(1, $data->get('first', 1));
});

it('can verify existence of property', function () {
    $data = new ObjectData([
        'first' => 1,
    ]);

    $this->assertTrue($data->has('first'));
    $this->assertFalse($data->has('second'));
});

it('can return multiple properties', function () {
    $data = new ObjectData($array = [
        'first' => 1,
        'second' => 2,
    ]);

    $this->assertEquals($array, $data->getMany(['first', 'second']));
});

it('can define macros', function () {
    ObjectData::macro('html', function () {
        return "<div>{$this->first}</div>";
    });

    $data = new ObjectData([
        'first' => 1
    ]);

    $this->assertEquals('<div>1</div>', $data->html());
});
