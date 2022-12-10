<?php

use NSRosenqvist\ApiToolkit\RetrospectiveResponse;
use NSRosenqvist\ApiToolkit\Tests\ClientFactory;

it('can review request from response object', function () {
    $client = (new ClientFactory())->create();
    $options = ['mock' => true];

    $promise = $client->requestAsync('PUT', $uri = 'first/second/3', $options);
    $result = $promise->wait();

    $this->assertTrue($result instanceof RetrospectiveResponse);
    $this->assertEquals($uri, (string) $result->getRequest()->getUri());
});
