<?php

use NSRosenqvist\ApiToolkit\ResponseFactory;
use NSRosenqvist\ApiToolkit\Structures\ListData;
use NSRosenqvist\ApiToolkit\Structures\ObjectData;
use NSRosenqvist\ApiToolkit\Structures\Result;

it('is countable', function () {
    $response = (new ResponseFactory())->create(200);

    // ListData
    $result = new Result($response, new ListData([
        ['first' => 1],
        ['second' => 2],
    ]));

    $this->assertEquals(2, count($result));

    // ObjectData
    $result = new Result($response, new ObjectData([
        'first' => 1,
        'second' => 2,
    ]));

    $this->assertEquals(1, count($result));

    // Empty
    $result = new Result($response);
    $this->assertEquals(0, count($result));
});

it('is iterable', function () {
    $response = (new ResponseFactory())->create(200);
    $result = new Result($response, new ListData([
        ['first' => 1],
        ['second' => 1],
    ]));

    $this->assertTrue((function () use ($result) {
        foreach ($result as $d) {
            return true;
        }
        return false;
    })());
});

it('supports array access', function () {
    $response = (new ResponseFactory())->create(200);
    $result = new Result($response, new ListData([
        $item = ['first' => 1],
        ['second' => 2],
    ]));

    $this->assertEquals(new ObjectData($item), $result[0]);
});

it('can return the result data', function () {
    $response = (new ResponseFactory())->create(200);
    $result = new Result($response, $data = 'data');

    $this->assertEquals($data, $result->getData());
});

it('can set new result data', function () {
    $response = (new ResponseFactory())->create(200);
    $result = new Result($response);
    $result->setData($data = 'data');

    $this->assertEquals($data, $result->getData());
});

it('can verify a successful response', function () {
    $response = (new ResponseFactory())->create(201);
    $result = new Result($response);

    $this->assertTrue($result->success());
    $response = (new ResponseFactory())->create(400);
    $result = new Result($response);
    $this->assertNotTrue($result->success());
});

it('can verify an unsuccessful response', function () {
    $response = (new ResponseFactory())->create(500);
    $result = new Result($response);

    $this->assertTrue($result->failure());
    $response = (new ResponseFactory())->create(200);
    $result = new Result($response);
    $this->assertNotTrue($result->failure());
});

it('can verify a redirect', function () {
    $response = (new ResponseFactory())->create(301);
    $result = new Result($response);

    $this->assertTrue($result->redirect());
    $response = (new ResponseFactory())->create(200);
    $result = new Result($response);
    $this->assertNotTrue($result->redirect());
});
