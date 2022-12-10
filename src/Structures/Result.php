<?php

namespace NSRosenqvist\ApiToolkit\Structures;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use NSRosenqvist\ApiToolkit\RetrospectiveResponse;
use NSRosenqvist\ApiToolkit\Traits\ExposesResponse;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Traversable;

class Result implements Countable, ResponseInterface, ArrayAccess, IteratorAggregate
{
    use ExposesResponse;

    /**
     * Wrapped response
     *
     * @var ResponseInterface
     */
    protected $response;

    /**
     * Data store
     *
     * @var mixed
     */
    protected $data;

    /**
     * @param ResponseInterface $response
     * @param mixed $data
     */
    public function __construct(ResponseInterface $response, $data = null)
    {
        $this->response = $response;
        $this->data = $data;
    }

    /**
     * Get request if it's available
     *
     * @return RequestInterface|null
     */
    public function getRequest(): ?RequestInterface
    {
        if ($this->response instanceof RetrospectiveResponse) {
            return $this->response->getRequest();
        }

        return null;
    }

    /**
     * Evaluate if request was successful
     *
     * @return boolean
     */
    public function success(): bool
    {
        $code = $this->getStatusCode();

        return ($code >= 200 && $code < 300)
            ? true
            : false;
    }

    /**
     * Evaluate if request was unsuccessful
     *
     * @return boolean
     */
    public function failure(): bool
    {
        return ! $this->success();
    }

    /**
     * Evaluate if response is a redirect
     *
     * @return boolean
     */
    public function redirect(): bool
    {
        $code = $this->getStatusCode();

        return ($code >= 300 && $code < 400)
            ? true
            : false;
    }

    /**
     * Return error if request was unsuccessful
     *
     * @return string|null
     */
    public function error(): ?string
    {
        return ($this->failure())
            ? ($this->getReasonPhrase() ?: 'Unkown error')
            : null;
    }

    /**
     * Set result data
     *
     * @param mixed $data
     * @return $this
     */
    public function setData($data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get result data
     *
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @return integer
     */
    public function count(): int
    {
        if (is_countable($this->data)) {
            return count($this->data);
        } elseif (! empty($this->data)) {
            return 1;
        }

        return 0;
    }

    /**
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        if (is_iterable($this->data)) {
            if ($this->data instanceof IteratorAggregate) {
                return $this->data->getIterator();
            } else {
                return new ArrayIterator($this->data);
            }
        } else {
            return new ArrayIterator();
        }
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset
     * @return boolean
     */
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }
}
