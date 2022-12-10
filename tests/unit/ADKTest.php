<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use NSRosenqvist\ApiToolkit\RequestChain;
use NSRosenqvist\ApiToolkit\Tests\InspectableApi;
use Psr\Http\Message\ResponseInterface;

it('can set stubs directory', function () {
    $api = new InspectableApi();
    $api->setDefaultContentDirectory(STUBS_ROOT);
    $this->assertEquals(STUBS_ROOT, $api->inspect('requestDefaults')['stubs'] ?? null);
});

it('can set mock manifest', function () {
    $api = new InspectableApi();
    $api->setDefaultManifest($path = 'foobar');
    $this->assertEquals($path, $api->inspect('requestDefaults')['manifest'] ?? null);
});

it('can create mock responses', function () {
    $api = new InspectableApi();
    $response = $api->response(301);
    $this->assertEquals(301, $response->getStatusCode());
});

it('exposes Guzzle client', function () {
    $api = new InspectableApi();
    $client = $api->client();
    $this->assertTrue($client instanceof Client);
});

it('can initiate a chain with caching enabled', function () {
    $api = new InspectableApi();
    $chain = $api->cache(true);

    $this->assertEquals(true, ($chain->getOptions())['cache'] ?? false);
    $this->assertTrue($chain instanceof RequestChain);
});

it('can toggle mocking', function () {
    $api = new InspectableApi();
    $api->mocking(true);

    $this->assertEquals(true, ($api->inspect('requestDefaults'))['mock'] ?? false);
});

it('can initiate a chain directly', function () {
    $api = new InspectableApi();
    $chain = $api->chain();
    $this->assertTrue($chain instanceof RequestChain);
});

it('can queue responses and retrieve responses from queue', function () {
    $api = new InspectableApi();
    $api->queue($api->response(301));
    $response = $api->{'foobar'}->get();

    $this->assertEquals(301, $response->getStatusCode());
});

it('can decode JSON appropriately', function () {
    $api = new InspectableApi();
    $type = 'application/json';
    $object = $object = (object) ['json' => true];
    $response = $api->response(200, json_encode($object), ['Content-Type' => $type]);

    $this->assertEquals($object, $api->decode($response->getBody(), $type));
});

it('can decode plain text appropriately', function () {
    $api = new InspectableApi();
    $text = 123;
    $type = 'text/plain';
    $response = $api->response(200, $text, ['Content-Type' => $type]);

    $this->assertEquals((string) $text, $api->decode($response->getBody()));
});

it('can extract response content type', function () {
    $api = new InspectableApi();
    $type = 'application/json';
    $object = $object = (object) ['json' => true];
    $response = $api->response(200, json_encode($object), ['Content-Type' => $type]);

    $this->assertEquals($type, strtolower($api->contentType($response)));
});

it('can resolve chain uri', function () {
    $api = new InspectableApi();
    $chain = $api->{'foo'}->{'bar'};
    $uri = $api->resolve($chain, []);

    $this->assertEquals('foo/bar', $uri);
});

it('can handle errors', function () {
    $api = new InspectableApi();
    $result = $api->mocking(true)->{'foobar'}->get();
    $exception = new RequestException('error', $result->getRequest());
    $this->assertTrue($api->errorHandler($exception, []) instanceof RequestException);
});

it('can send synchronous requests', function () {
    $api = new InspectableApi();
    $result = $api->mocking(true)->{'foobar'}->get();
    $this->assertTrue($result instanceof ResponseInterface);
});

it('can send asynchronous requests', function () {
    $api = new InspectableApi();

    $promise = $api->mocking(true)->{'foobar'}->getAsync();
    $this->assertTrue($promise instanceof PromiseInterface);

    $response = $promise->wait();
    $this->assertTrue($response instanceof ResponseInterface);
});

it('can initiate a chain through property access', function () {
    $api = new InspectableApi();
    $chain = $api->{'foobar'};
    $this->assertTrue($chain instanceof RequestChain);
});

it('can call guzzle directly', function () {
    $api = new InspectableApi();
    $api->mocking(true);
    $response = $api->get('/foobar');
    $this->assertTrue($response instanceof ResponseInterface);
});

it('can call guzzle request/requestAsync', function () {
    $api = new InspectableApi();
    $api->mocking(true);

    $response = $api->request('GET', '/foobar');
    $this->assertTrue($response instanceof ResponseInterface);

    $response = $api->requestAsync('GET', '/foobar');
    $this->assertTrue($response instanceof PromiseInterface);
});
