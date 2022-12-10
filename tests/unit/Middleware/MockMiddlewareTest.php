<?php

use GuzzleHttp\Psr7\Response;
use NSRosenqvist\ApiToolkit\Tests\ClientFactory;

it('can mock a response', function () {
    $client = (new ClientFactory())->create();

    $response = $client->request('GET', 'first/second/3', [
        'mock' => true,
        'response' => new Response(200),
    ]);

    $this->assertEquals(200, $response->getStatusCode());
});

it('can mock an asynchronous response', function () {
    $client = (new ClientFactory())->create();

    $promise = $client->requestAsync('GET', 'first/second/3', [
        'mock' => true,
        'response' => new Response(200),
    ]);
    $response = $promise->wait();

    $this->assertEquals(200, $response->getStatusCode());
});


it('can mock a response from manifest', function () {
    $client = (new ClientFactory())->create();

    $response = $client->request('GET', 'top/medium/low', [
        'mock' => true,
        'manifest' => MANIFESTS_ROOT . '/simple.json',
    ]);

    $this->assertEquals(201, $response->getStatusCode());
});

it('can handle non-existing mock manifest routes', function () {
    $client = (new ClientFactory())->create();

    $response = $client->request('GET', 'not/defined', [
        'mock' => true,
        'manifest' => MANIFESTS_ROOT . '/simple.json',
    ]);

    $this->assertEquals(200, $response->getStatusCode());
});
