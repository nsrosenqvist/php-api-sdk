<?php

use NSRosenqvist\ApiToolkit\Structures\ListData;
use NSRosenqvist\ApiToolkit\Structures\ObjectData;
use NSRosenqvist\ApiToolkit\Tests\DriverImplementation;

it('can return appropriate result type', function () {
    $api = new DriverImplementation();

    $object = (object) ['foo' => 'bar'];
    $headers = ['Content-Type' => 'application/json'];
    $response = $api->response(200, json_encode($object), $headers);
    $result = $api->resultHandler($response, []);
    $this->assertTrue($result->getData() instanceof ObjectData);

    $array = [(object) ['foo' => 'bar']];
    $headers = ['Content-Type' => 'application/json'];
    $response = $api->response(200, json_encode($array), $headers);
    $result = $api->resultHandler($response, []);
    $this->assertTrue($result->getData() instanceof ListData);
});
