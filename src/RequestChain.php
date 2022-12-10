<?php

namespace NSRosenqvist\ApiToolkit;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use NSRosenqvist\ApiToolkit\ADK;
use Psr\Http\Message\UriInterface;

/**
 * @method ResponseInterface get(array $options = [])
 * @method PromiseInterface getAsync(array $options = [])
 * @method ResponseInterface delete(array $options = [])
 * @method PromiseInterface deleteAsync(array $options = [])
 * @method ResponseInterface head(array $options = [])
 * @method PromiseInterface headAsync(array $options = [])
 * @method ResponseInterface options(array $options = [])
 * @method PromiseInterface optionsAsync(array $options = [])
 * @method ResponseInterface patch(array $options = [])
 * @method PromiseInterface patchAsync(array $options = [])
 * @method ResponseInterface post(array $options = [])
 * @method PromiseInterface postAsync(array $options = [])
 * @method ResponseInterface put(array $options = [])
 * @method PromiseInterface putAsync(array $options = [])
 * @method ResponseInterface trace(array $options = [])
 * @method PromiseInterface traceAsync(array $options = [])
 */
class RequestChain
{
    /**
     * Origin driver
     *
     * @var ADK
     */
    protected $api;

    /**
     * Guzzle client
     *
     * @var Client
     */
    protected $client;

    /**
     * Request options
     *
     * @var array
     */
    protected $options = [];

    /**
     * Chain links
     *
     * @var array
     */
    protected $links = [];

    /**
     * @param ADK $api
     * @param Client $client
     * @param array $options
     * @param string|array|UriInterface $uri
     */
    public function __construct(
        ADK $api,
        Client $client,
        array $options = [],
        $uri = null
    ) {
        $this->api = $api;
        $this->client = $client;
        $this->options = $options;

        if (! empty($uri)) {
            if (is_string($uri) || $uri instanceof UriInterface) {
                $uri = Psr7\Utils::uriFor($uri);
                $path = $uri->getPath();
                $query = $uri->getQuery();
                parse_str($query, $query);

                $this->links = array_filter(explode('/', $path));
                $this->options['query'] = array_merge($this->options['query'] ?? [], $query);
            } elseif (is_array($uri)) {
                $this->links = array_filter($uri);
            }
        }
    }

    /**
     * Get request client
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Get request options
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set request options
     *
     * @param array $options
     * @return self
     */
    public function options(array $options): self
    {
        return new self($this->api, $this->client, $options, $this->links);
    }

    /**
     * Set a specific request option
     *
     * @param string $option
     * @param mixed $value
     * @return self
     */
    public function option(string $option, $value): self
    {
        $options = $this->options;
        $options[$option] = $value;

        return new self($this->api, $this->client, $options, $this->links);
    }

    /**
     * Set query option
     *
     * @param array $query
     * @return self
     */
    public function query(array $query): self
    {
        $options = $this->options;
        $options['query'] = $query;

        return new self($this->api, $this->client, $options, $this->links);
    }

    /**
     * Set specific query arg
     *
     * @param string $arg
     * @param mixed $value
     * @return self
     */
    public function queryArg(string $arg, $value): self
    {
        $options = $this->options;
        $options['query'] = $options['query'] ?? [];
        $options['query'][$arg] = $value;

        return new self($this->api, $this->client, $options, $this->links);
    }

    /**
     * Resolve the chain
     *
     * @return string
     */
    public function resolve(): string
    {
        return implode('/', $this->links);
    }

    /**
     * @param string $property
     * @return self
     */
    public function __get(string $property): self
    {
        $links = $this->links;
        $links[] = $property;

        return new self($this->api, $this->client, $this->options, $links);
    }

    /**
     * @param string $method
     * @param array $arguments
     * @return void
     */
    public function __call(string $method, array $arguments = [])
    {
        if (method_exists($this->api, $method)) {
            return $this->api->{$method}($this, ...$arguments);
        } else {
            return $this->api->{$method}(new self(
                $this->api,
                $this->client,
                array_merge($this->options, $arguments[0] ?? []),
                $this->links
            ));
        }
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->resolve();
    }
}
