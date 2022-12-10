<?php

namespace NSRosenqvist\ApiToolkit;

use Psr\Http\Message\ResponseInterface;

interface ResponseFactoryInterface
{
    /**
     * Set default stubs directory
     *
     * @param string|null $dir
     * @return self
     */
    public function setDefaultContentDirectory(?string $dir): self;

    /**
     * Create a response
     *
     * @param int $code
     * @param string $content
     * @param array $headers
     * @param string $version
     * @param string|null $reason
     * @param string|null $dir
     * @return ResponseInterface
     */
    public function default(): ResponseInterface;

    /**
     * Get default response
     *
     * @return ResponseInterface
     */
    public function create(
        int $code,
        $content = '',
        array $headers = [],
        string $version = '1.1',
        ?string $reason = null,
        ?string $dir = null
    ): ResponseInterface;
}
