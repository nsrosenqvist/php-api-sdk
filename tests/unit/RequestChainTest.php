<?php

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use NSRosenqvist\ApiToolkit\RequestChain;
use NSRosenqvist\ApiToolkit\Tests\DriverImplementation;

it('can chain URIs', function () {
    $chain = new RequestChain(new DriverImplementation(), new Client());
    $chain = $chain->first->second->{3};
    $this->assertEquals('first/second/3', strval($chain));
});

it('can branch URIs', function () {
    $chain = new RequestChain(new DriverImplementation(), new Client());
    $chain = $chain->first;
    $direct = $chain->{3};
    $proper = $chain->second->{3};
    $this->assertNotEquals(strval($proper), strval($direct));
});

it('can apply a predefined URI', function () {
    $uri = 'first/second/3';
    $chain = new RequestChain(new DriverImplementation(), new Client(), [], $uri);
    $this->assertEquals($uri, strval($chain));
});

it('can pass itself on to the request handler', function () {
    $chain = new RequestChain(new DriverImplementation(), new Client());
    $this->assertTrue($chain->getAsync() instanceof PromiseInterface);
});

it('can pass itself on to a custom driver method', function () {
    $chain = new RequestChain(new DriverImplementation(), new Client());
    $this->assertTrue($chain->custom());
});
