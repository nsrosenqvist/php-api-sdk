<?php

namespace NSRosenqvist\ApiToolkit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @method PromiseInterface sendAsync(RequestInterface $request, array $options = [])
 * @method ResponseInterface send(RequestInterface $request, array $options = [])
 * @method ResponseInterface sendRequest(RequestInterface $request)
 * @method PromiseInterface requestAsync(string|RequestChain $method, $uri = '', array $options = [])
 * @method ResponseInterface request(string|RequestChain $method, $uri = '', array $options = [])
 * @method mixed getConfig(?string $option = null)
 * @method ResponseInterface get(string|RequestChain $uri, array $options = [])
 * @method PromiseInterface getAsync(string|RequestChain $uri, array $options = [])
 * @method ResponseInterface delete(string|RequestChain $uri, array $options = [])
 * @method PromiseInterface deleteAsync(string|RequestChain $uri, array $options = [])
 * @method ResponseInterface head(string|RequestChain $uri, array $options = [])
 * @method PromiseInterface headAsync(string|RequestChain $uri, array $options = [])
 * @method ResponseInterface options(string|RequestChain $uri, array $options = [])
 * @method PromiseInterface optionsAsync(string|RequestChain $uri, array $options = [])
 * @method ResponseInterface patch(string|RequestChain $uri, array $options = [])
 * @method PromiseInterface patchAsync(string|RequestChain $uri, array $options = [])
 * @method ResponseInterface post(string|RequestChain $uri, array $options = [])
 * @method PromiseInterface postAsync(string|RequestChain $uri, array $options = [])
 * @method ResponseInterface put(string|RequestChain $uri, array $options = [])
 * @method PromiseInterface putAsync(string|RequestChain $uri, array $options = [])
 * @method ResponseInterface trace(string|RequestChain $uri, array $options = [])
 * @method PromiseInterface traceAsync(string|RequestChain $uri, array $options = [])
 *
 * @see \GuzzleHttp\Client
 */
class ADK
{
    /**
     * API Client
     *
     * @var Client
     */
    protected $client;

    /**
     * Client options
     *
     * @var array
     */
    protected $clientOptions = [];

    /**
     * Default request options
     *
     * @var array
     */
    protected $requestDefaults = [];

    /**
     * @param array $clientOptions
     * @param array $requestDefaults
     */
    public function __construct(
        array $clientOptions = [],
        array $requestDefaults = []
    ) {
        $this->clientOptions = $clientOptions;
        $this->requestDefaults = $requestDefaults;
    }

    /**
     * Get API client
     *
     * @return Client
     */
    public function client(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        return $this->client = new Client(
            array_merge(
                $this->clientOptions,
                ['handler' => $this -> handlerStack()]
            )
        );
    }

    /**
     * Get handler stack
     *
     * @return HandlerStack
     */
    protected function handlerStack(): HandlerStack
    {
        return HandlerStack::create();
    }

    /**
     * Initiate a new request chain
     *
     * @param array $options
     * @param Psr\Http\Message\UriInterface|string|null $uri
     * @return RequestChain
     */
    public function chain(array $options = [], $uri = null): RequestChain
    {
        $client = $this->client();
        $options = array_merge($this->requestDefaults, $options);

        return (new RequestChain($this, $client, $options, $uri));
    }

    /**
     * Decode stream data
     *
     * @param StreamInterface $stream
     * @param string $type
     * @return void
     */
    public function decode(StreamInterface $stream, string $type = 'text/plain')
    {
        $type = strtolower($type);

        // TODO: Support more
        if ($type === 'application/json') {
            return json_decode($stream);
        } elseif (substr($type, 0, 5) === 'text/') {
            return (string) $stream;
        }

        return $stream;
    }

    /**
     * Determine response content type
     *
     * @param ResponseInterface $response
     * @return string
     */
    public function contentType(ResponseInterface $response): string
    {
        $headers = array_change_key_case($response->getHeaders(), CASE_LOWER);
        $type = 'text/plain';

        if (isset($headers['content-type'])) {
            $type = (is_array($headers['content-type']))
                ? current($headers['content-type'])
                : $headers['content-type'];
        }

        return $type;
    }

    /**
     * Resolve/modify the URI based on a provided endpoint and the request options.
     *
     * @param RequestChain $chain
     * @return string
     */
    public function resolve(RequestChain $chain): string
    {
        return $chain->resolve();
    }

    /**
     * Default success handler.
     *
     * @param ResponseInterface $response
     * @param array $options
     * @return ResponseInterface
     */
    public function resultHandler(ResponseInterface $response, array $options): ResponseInterface
    {
        return $response;
    }

    /**
     * Default handler for API-exceptions
     *
     * @param RequestException $reason
     * @param array $options
     * @return RequestException
     */
    public function errorHandler(RequestException $reason, array $options): RequestException
    {
        return $reason;
    }

    /**
     * Process options before executing request
     *
     * @param array $options
     * @return array
     */
    protected function processOptions(array $options): array
    {
        return $options;
    }

    /**
     * Accessing a non-existent property initiates a RequestChain sequence.
     *
     * @param string $property
     * @return RequestChain
     */
    public function __get(string $property): RequestChain
    {
        return ($this->chain())->{$property};
    }

    /**
     * Processes argument list and normalizing the request format
     *
     * @param string $method Guzzle method
     * @param array $arguments Method arguments
     * @return array
     */
    protected function processArguments(string $method, array $arguments): array
    {
        if (($chain = $arguments[0]) instanceof RequestChain) {
            $endpoint = $this->resolve($chain);
            $options = $chain->getOptions();
            $options['endpoint'] = $endpoint;
            $options = $this->processOptions($options);

            $arguments = [$chain->getClient(), $endpoint, $options];
        } else {
            $optionIndex = (substr($method, 0, 7) === 'request') ? 2 : 1;
            $endpointIndex = $optionIndex - 1;
            $options = $arguments[$optionIndex] ?? [];
            $endpoint = $arguments[$endpointIndex];

            // Absolute URLs are never processed as chains
            if (strtolower(substr($arguments[$endpointIndex], 0, 4)) === 'http') {
                $options = $this->processOptions(array_merge($this->requestDefaults, $options, [
                    'url' => $arguments[$endpointIndex],
                ]));
            } else {
                $chain = $this->chain($options, $arguments[$endpointIndex]);
                $endpoint = $this->resolve($chain);
                $options = $chain->getOptions();
                $options['endpoint'] = $endpoint;
                $options = $this->processOptions($options);
            }

            $arguments[$optionIndex] = $options;
            $arguments[$endpointIndex] = $endpoint;
            array_unshift($arguments, $this->client());
        }

        return $arguments;
    }

    /**
     * Undefined methods are executed on the client object.
     * Before passing it on, we also make sure that handlers
     * are attached appropriately.
     *
     * @param string $method
     * @param array $arguments
     * @return void
     */
    public function __call(string $method, array $arguments = [])
    {
        // send* can get passed onto Guzzle directly
        if (in_array($method, ['send', 'sendAsync', 'sendRequest', 'getConfig'])) {
            return ($this->client())->{$method}(...$arguments);
        }

        // Verify method exists
        $arguments = $this->processArguments($method, $arguments);
        $client = array_shift($arguments);
        $options = (substr($method, 0, 7) === 'request')
            ? $arguments[2]
            : $arguments[1];

        if (! method_exists($client, $method)) {
            throw new \BadMethodCallException("Method \"$method\" doesn't exist on " . get_class($client));
        }

        // Execute request and handle results. If async, add chained handlers
        $errorHandler = [$this, 'errorHandler'];
        $resultHandler = [$this, 'resultHandler'];
        $guzzle = [$client, $method];

        try {
            $return = $guzzle(...$arguments);

            if ($return instanceof PromiseInterface) {
                return $return->then(
                    function (?ResponseInterface $response) use ($resultHandler, $options) {
                        return $resultHandler($response, $options);
                    },
                    function ($reason) use ($errorHandler, $options) {
                        return Promise\Create::rejectionFor($errorHandler($reason, $options));
                    }
                );
            } else {
                return $resultHandler($return, $options);
            }
        } catch (RequestException $reason) {
            return $errorHandler($reason, $options);
        }
    }
}
