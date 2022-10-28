<?php

namespace NSRosenqvist\APIcly;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Promise\PromiseInterface;

use GuzzleHttp\Psr7\Response;

class API {
    public $client;

    protected array $options = [];
    protected array $defaults = [];
    protected bool $cache = true;
    public array $results = [];
    public $cacheHandler = CacheHandler::class;

    /**
     * Create an API-instance.
     *
     * @param Client $client Guzzle client
     * @param array $options Options passed to API
     */
    public function __construct(Client $client, array $options = [])
    {
        $this->client = $client;
        $this->options = array_merge($this->defaults, $options);
    }

    /**
     * Initiates a PreparedRequest with a custom cache-directive.
     *
     * @param bool $cache Whether the result should be cached or not.
     * @return PreparedRequest
     */
    public function cache(bool $cache): self
    {
        return (new PreparedRequest($this, $this->defaults, $cache));
    }

    public function mock()
    {
        return (new PreparedRequest($this, $this->defaults, $this->cache));
    }

    /**
     * Default success handler.
     *
     * @param ResponseInterface $response
     * @param int|null $index Request index, this is passed when handling
     *                        a batch- or a pool-request.
     * @return Result
     */
    public function successHandler(ResponseInterface $response, $index = null): Result
    {
        return new Result($response);
    }

    /**
     * Default handler for API-exceptions.
     *
     * @param RequestException $reason
     * @param int|null $index Request index, this is passed when handling
     *                        a batch- or a pool-request.
     * @return Result
     */
    public function errorHandler(RequestException $reason, $index = null): Result
    {
        return new Result($reason->getResponse());
    }

    /**
     * Modify a PreparedRequest according to user-defined rules.
     *
     * @param PreparedRequest $request A PreparedRequest object passed by reference
     * @return void
     */
    public function morphRequest(PreparedRequest &$request)
    {
        $links = &$request->links;
        $opts = &$request->options;

        if (count($links) >= 3 && $links[0] === 'companies' && $links[2] === 'reviews') {
            $opts['company_id'] = $links[1];
            $links = ['reviews'];
        }
    }

    /**
     * Execute a request-pool against the API.
     * 
     * @param array|\Iterator $requests Requests or functions that return requests to send concurrently.
     * @param int $concurrency Number of concurrent requests
     * @return Promise
     */
    public function pool($requests, int $concurrency = 5): PromiseInterface
    {
        $pool = new Pool($this->client, $requests, [
            'concurrency' => $concurrency,
            'fulfilled' => [$this, 'successHandler'],
            'rejected' => [$this, 'errorHandler'],
        ]);

        return $pool->promise();
    }

    public function send(PreparedRequest $request)
    {
        // Fetch cached result
        $request_id = (string) $request;
        $use_cache = ($request->cache && $request->isGetRequest());
        $has_cache = isset($this->results[$request_id]);
        $cache_value = ($has_cache) ? $this->results[$request_id] : null;

        if ($use_cache && $has_cache && $request->mode === 'sync') {
            return $cache_value;
        }

        // Configure handlers
        $errorHandler = [$this, 'errorHandler'];
        $successHandler = [$this, 'successHandler'];

        if ($use_cache) {
            if (is_callable($this->cacheHandler)) {
                $successHandler = $this->cacheHandler;
            } else {
                if (is_string($this->cacheHandler) && is_a($this->cacheHandler, HandlerInterface::class, true)) {
                    $successHandler = new $this->cacheHandler();
                } else {
                    $successHandler = new CacheHandler();
                }

                $successHandler->setAPI($this);
                $successHandler->setRequestId($request_id);
            }
        }

        // Response middleware -> make Result
        // $stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
        //     return $response->withHeader('X-Foo', 'bar');
        // }));

        // Rate limit handler
        // $stack = HandlerStack::create();
        // $stack->push(RateLimiterMiddleware::perSecond(3));

        // $client = new Client([
        //     'handler' => $stack,
        // ]);

        // Autoretry
        // use GuzzleRetry\GuzzleRetryMiddleware;

        // $stack = HandlerStack::create();
        // $stack->push(GuzzleRetryMiddleware::factory());

        // $client = new Client(['handler' => $stack]);

        try {
            $guzzle_method = strtolower($request->method);
            $guzzle_method .= ($request->mode === 'async') ? 'Async' : '';

            // If async, return promise after registering handlers
            if ($request->mode === 'async') {
                if ($use_cache && $has_cache) {
                    $promise = new Promise(function () use (&$promise, &$cache_value) {
                        $promise->resolve($cache_value);
                    });
                } else {
                    echo "HERE";
                    // $promise = $this->client->{$guzzle_method}($request->path, $request->options);
                    // $promise->then($successHandler, $errorHandler);

                    $request_promise = $this->client->{$guzzle_method}($request->path, $request->options);
                    $request_promise->then($successHandler, $errorHandler);

                    $promise = new Promise(function () use (&$promise, &$request_promise) {
                        $promise->resolve($request_promise);
                    });
                    // $promise = new Promise(function () use (&$promise, &$request) {
                    //     $request_promise = $this->client->{$guzzle_method}($request->path, $request->options);
                    //     $request_promise
                    //     $promise->resolve($cache_value);
                    // });
                    // $promise = $this->client->{$guzzle_method}($request->path, $request->options);
                    // $promise->then(function (ResponseInterface $response) use (&$promise, &$successHandler) {
                    //     $promise->resolve($successHandler($response));
                    // }, $errorHandler);
                }

                return $promise;
            }

            $result = $successHandler($response);
        } catch(RequestException $e) {
            $result = $errorHandler($e);
        }

        return $result;
    }

    /**
     * Construct the full request URI from a provided endpoint
     *
     * @param string $uri The API endpoint
     * @return string The full request URI
     */
    public function build(string $uri = ''): string
    {
        $base_url = implode('/', array_intersect_key($this->options, array_flip(['base_url', 'version'])));
        
        return $base_url .= (! empty($uri)) ? '/' . $uri : '';
    }

    /**
     * Accessing a non-existent property initiates a PreparedRequest sequence.
     *
     * @param [type] $property
     * @return PreparedRequest
     */
    public function __get(string $property): PreparedRequest
    {
        return (new PreparedRequest($this, $this->defaults, $this->cache))->{$property};
    }
}