<?php

namespace NSRosenqvist\ApiToolkit\Traits;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

trait ExposesResponse
{
    /**
     * @return integer
     */
    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * @return string
     */
    public function getReasonPhrase(): string
    {
        return $this->response->getReasonPhrase();
    }

    /**
     * @param int $code
     * @param string $reasonPhrase
     * @return ResponseInterface
     */
    public function withStatus($code, $reasonPhrase = ''): ResponseInterface
    {
        $response = $this->response->withStatus($code, $reasonPhrase);

        return new self($response, $this->data);
    }

    /**
     * @return string
     */
    public function getProtocolVersion(): string
    {
        return $this->response->protocol;
    }

    /**
     * @param string $version
     * @return MessageInterface
     */
    public function withProtocolVersion($version): MessageInterface
    {
        $response = $this->response->withProtocolVersion($version);

        return new self($response, $this->data);
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->response->getHeaders();
    }

    /**
     * @param string $header
     * @return boolean
     */
    public function hasHeader($header): bool
    {
        return $this->response->hasHeader($header);
    }

    /**
     * @param string $header
     * @return array
     */
    public function getHeader($header): array
    {
        return $this->response->getHeader($header);
    }

    /**
     * @param string $header
     * @return string
     */
    public function getHeaderLine($header): string
    {
        return implode(', ', $this->response->getHeader($header));
    }

    /**
     * @param string $header
     * @param mixed $value
     * @return MessageInterface
     */
    public function withHeader($header, $value): MessageInterface
    {
        $response = $this->response->withHeader($header, $value);

        return new self($response, $this->data);
    }

    /**
     * @param string $header
     * @param mixed $value
     * @return MessageInterface
     */
    public function withAddedHeader($header, $value): MessageInterface
    {
        $response = $this->response->withAddedHeader($header, $value);

        return new self($response, $this->data);
    }

    /**
     * @param string $header
     * @return MessageInterface
     */
    public function withoutHeader($header): MessageInterface
    {
        $response = $this->response->withoutHeader($header);

        return new self($response, $this->data);
    }

    /**
     * @return StreamInterface
     */
    public function getBody(): StreamInterface
    {
        return $this->response->getBody();
    }

    /**
     * @param StreamInterface $body
     * @return MessageInterface
     */
    public function withBody(StreamInterface $body): MessageInterface
    {
        $response = $this->response->withBody($body);

        return new self($response, $this->data);
    }
}
