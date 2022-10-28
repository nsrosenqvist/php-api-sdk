<?php

namespace NSRosenqvist\APIcly;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

class PreparedRequest {
    protected $api;
    public $method = 'GET';
    public $mode = 'sync';
    public $path = '';
    protected $uri = '';
    public $options = [];
    public $links = [];
    public $cache = false;

    const METHODS = [
        'GET',
        'DELETE',
        'HEAD',
        'OPTIONS',
        'PATCH',
        'POST',
        'PUT'
    ];

    const GET = [
        'GET',
        'HEAD',
        'OPTIONS'
    ];

    const MODES = [
        'sync',
        'async'
    ];

    public function __construct(API $api, array $defaults = [], $cache = false)
    {
        $this->api = $api;
        $this->options = $defaults;
        $this->cache = $cache;
    }

    public function __get($property): self
    {
        $this->links[] = $property;
        $clone = clone $this;
        array_pop($this->links);
        
        return $clone;
    }

    public function isGetRequest(): bool
    {
        return in_array($this->method, self::GET);
    }

    public function method($method): self
    {
        if (! in_array($method = strtoupper($method), self::METHODS)) {
            throw new InvalidArgumentException($method . ' is not a valid HTTP method.');
        }

        $this->method = $method;

        return $this;
    }

    public function mode($mode): self
    {
        if (! in_array($mode = strtolower($mode), self::MODES)) {
            throw new InvalidArgumentException($mode . ' is not a valid request mode.');
        }

        $this->mode = $mode;

        return $this;
    }

    public function __call(string $name, array $arguments = [])
    {
        $this->options = array_merge($this->options, $arguments);
        $this->api->morphRequest($this);

        $this->uri = implode('/', $this->links);
        $this->path = $this->api->build($this->uri);

        if (method_exists($this->api, $name)) {
            return call_user_func([$this->api, $name], $this);
        } else {
            list($method, $mode) = array_merge(self::splitCamelCase($name), ['sync']);

            $this->method($method);
            $this->mode($mode);

            return $this->api->send($this);
        }
    }

    public function send()
    {
        return $this->api->send($this);
    }

    public function __toString(): string
    {
        return md5($this->method . ' ' . $this->path . ' '. var_export($this->options, true));
    }

    public function STREAMFOR()
    {
        
    }

    /**
     * @see https://stackoverflow.com/a/23028424
     */
    protected static function splitCamelCase(string $input): array
    {
        return preg_split(
            '/(^[^A-Z]+|[A-Z][^A-Z]+)/',
            $input,
            -1, /* no limit for replacement count */
            PREG_SPLIT_NO_EMPTY /*don't return empty elements*/
                | PREG_SPLIT_DELIM_CAPTURE /*don't strip anything from output array*/
        );
    }
}