<?php

namespace NSRosenqvist\ApiToolkit\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Promise;
use NSRosenqvist\ApiToolkit\RetrospectiveResponse;
use Psr\Http\Message\ResponseInterface;

class RecorderMiddleware
{
    /**
     * Next handler
     *
     * @var callable
     */
    protected $nextHandler;

    /**
     * @param callable
     */
    public function __construct(callable $nextHandler)
    {
        $this->nextHandler = $nextHandler;
    }

    /**
     * Middleware main function
     *
     * @param RequestInterface $request
     * @param array $options
     * @return PromiseInterface
     */
    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        return ($this->nextHandler)($request, $options)->then(
            function (?ResponseInterface $response) use ($request) {
                return Promise\Create::promiseFor(new RetrospectiveResponse($request, $response));
            }
        );
    }
}
