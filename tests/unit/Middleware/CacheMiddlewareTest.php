<?php

use GuzzleHttp\Psr7\Response;
use NSRosenqvist\ApiToolkit\Tests\ClientFactory;

it('can cache various GET requests', function () {
    $client = (new ClientFactory()) -> create();

    $response = $client->request('GET', 'first/second/3', [
        'cache' => true,
        'mock' => true,
        'response' => new Response(200),
    ]);

    $response = $client->request('GET', 'first/second/3', [
        'cache' => true,
        'mock' => true,
        'response' => new Response(500),
    ]);

    $this->assertEquals(200, $response->getStatusCode());
});

it('can bypass cache if not a GET request', function () {
    $client = (new ClientFactory()) -> create();

    $response = $client->request('GET', 'first/second/3', [
        'cache' => true,
        'mock' => true,
        'response' => new Response(200),
    ]);

    $response = $client->request('POST', 'first/second/3', [
        'cache' => true,
        'mock' => true,
        'response' => new Response(301),
    ]);

    $this->assertEquals(301, $response->getStatusCode());
});

it('can cache multiple requests to same endpoint but with different queries', function () {
    $client = (new ClientFactory()) -> create();

    $response = $client->request('GET', 'first/second/3', [
        'cache' => true,
        'mock' => true,
        'response' => new Response(200),
    ]);

    $response = $client->request('GET', 'first/second/3', [
        'query' => ['first' => 1],
        'cache' => true,
        'mock' => true,
        'response' => new Response(301),
    ]);

    $this->assertEquals(301, $response->getStatusCode());
});

it('can identify cache items with differently ordered queries', function () {
    $client = (new ClientFactory()) -> create();

    $response = $client->request('GET', 'first/second/3', [
        'cache' => ['first' => 1, 'second' => 2],
        'mock' => true,
        'response' => new Response(200),
    ]);

    $response = $client->request('GET', 'first/second/3', [
        'query' => ['second' => 2, 'first' => 1],
        'cache' => true,
        'mock' => true,
        'response' => new Response(301),
    ]);

    $this->assertEquals(301, $response->getStatusCode());
});
