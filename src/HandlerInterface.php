<?php

namespace NSRosenqvist\APIcly;

use Psr\Http\Message\ResponseInterface;

interface HandlerInterface
{
    function setAPI(API $api): void;

    function setRequestId(string $request_id): void;

    function __invoke(ResponseInterface $response, $index = null): Result;
}