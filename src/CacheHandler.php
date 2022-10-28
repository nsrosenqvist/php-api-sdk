<?php

namespace NSRosenqvist\APIcly;

use Psr\Http\Message\ResponseInterface;

class CacheHandler implements HandlerInterface
{
    protected $api;
    protected $request_id;

    function setAPI(API $api): void
    {
        $this->api = $api;
    }

    function setRequestId(string $request_id): void
    {
        $this->request_id = $request_id;
    }

    function exists(): bool
    {

    }

    function get(): Result
    {
        // return $this->
    }

    public function __invoke(ResponseInterface $response, $index = null): Result
    {
        return $this->api->results[$this->request_id] = $this->api->successHandler($response, $index);
    }
}