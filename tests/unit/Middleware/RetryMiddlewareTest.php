<?php

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use NSRosenqvist\ApiToolkit\Middleware\RetryMiddleware;

it('can retry a request', function () {
    $max_retries = 1;
    $retries = 0;
    $stack = HandlerStack::create();
    $stack->push(function (callable $handler) use (&$retries, $max_retries) {
        return new RetryMiddleware(
            $handler,
            function ($options, $request, $response, $exception) use (&$retries, $max_retries) {
                if ($response->getStatusCode() === 429 && $retries < $max_retries) {
                    $retries++;
                    return true;
                }
                return false;
            }
        );
    });

    $client = new Client([
        'handler' => $stack,
        'http_errors' => false
    ]);

    $response = $client->get('https://httpbin.org/status/429', ['max_retries' => $max_retries]);

    $this->assertEquals($max_retries, $retries);
})->skip(! getenv('TEST_REMOTE'), 'Only testing local requests');
