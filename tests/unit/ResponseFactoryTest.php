<?php

use NSRosenqvist\ApiToolkit\ResponseFactory;

it('can create response with set status code', function () {
    $factory = new ResponseFactory();
    $response = $factory->create(301);
    $this->assertEquals(301, $response->getStatusCode());
});

it('can create response with set body', function () {
    $factory = new ResponseFactory();
    $response = $factory->create(200, 'custom');
    $this->assertEquals('custom', $response->getBody());
});

it('can create response with set header', function () {
    $factory = new ResponseFactory();
    $response = $factory->create(200, '', ['Content-Type' => 'custom']);
    $headers = $response->getHeader('Content-Type');
    $this->assertEquals('custom', current($headers));
});

it('can load body from file contents', function () {
    $factory = new ResponseFactory(STUBS_ROOT);
    $response = $factory->create(200, 'single.json');
    $contents = file_get_contents(STUBS_ROOT . '/single.json');
    $this->assertEquals($contents, $response->getBody());
});

it('can create response with object body', function () {
    $factory = new ResponseFactory();
    $response = $factory->create(200, $object = (object) [
        'foo' => 'bar',
    ]);
    $contents = json_decode($response->getBody());
    $this->assertEquals($object, $contents);
});

it('can automatically set content type header from file mime type', function () {
    $factory = new ResponseFactory(STUBS_ROOT);
    $response = $factory->create(200, 'single.json');
    $headers = $response->getHeader('Content-Type');
    $this->assertEquals('application/json', current($headers));
});
