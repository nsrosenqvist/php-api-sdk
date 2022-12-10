<?php

namespace NSRosenqvist\ApiToolkit;

use NSRosenqvist\ApiToolkit\Traits\ExposesResponse;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RetrospectiveResponse implements ResponseInterface
{
    use ExposesResponse;

    /**
     * Request
     *
     * @var RequestInterface
     */
    protected $request;

    /**
     * Response
     *
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     */
    public function __construct(RequestInterface $request, ?ResponseInterface $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Get request
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get response
     *
     * @return ResponseInterface|null
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
