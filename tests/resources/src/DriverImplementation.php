<?php

namespace NSRosenqvist\ApiToolkit\Tests;

use NSRosenqvist\ApiToolkit\StandardApi;
use NSRosenqvist\ApiToolkit\RequestChain;
use NSRosenqvist\ApiToolkit\Structures\ListData;
use NSRosenqvist\ApiToolkit\Structures\ObjectData;
use NSRosenqvist\ApiToolkit\Structures\Result;
use Psr\Http\Message\ResponseInterface;

class DriverImplementation extends StandardApi
{
    // ...

    public function custom(?RequestChain $chain): bool
    {
        return $chain instanceof RequestChain;
    }

    public function resultHandler(ResponseInterface $response, array $options): Result
    {
        $type = $this->contentType($response);
        $data = $this->decode($response->getBody(), $type);

        if (is_array($data)) {
            $data = new ListData($data);
        } elseif (is_object($data)) {
            $data = new ObjectData($data);
        }

        return new Result($response, $data);
    }
}
