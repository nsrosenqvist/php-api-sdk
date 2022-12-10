<?php

namespace NSRosenqvist\ApiToolkit\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use NSRosenqvist\ApiToolkit\Middleware\CacheMiddleware;
use NSRosenqvist\ApiToolkit\Middleware\MockMiddleware;
use NSRosenqvist\ApiToolkit\Middleware\RecorderMiddleware;
use NSRosenqvist\ApiToolkit\Middleware\RetryMiddleware;
use NSRosenqvist\ApiToolkit\ResponseFactory;

class ClientFactory
{
    public function create(string $resources = ''): Client
    {
        $stack = HandlerStack::create();

        $stack->push(function (callable $handler) {
            return new RecorderMiddleware($handler);
        });
        $stack->push(function (callable $handler) {
            return new CacheMiddleware($handler);
        });
        $stack->push(function (callable $handler) use ($resources) {
            return new MockMiddleware($handler, new ResponseFactory($resources));
        });

        return new Client([
            'handler' => $stack,
            'http_errors' => false
        ]);
    }
}
