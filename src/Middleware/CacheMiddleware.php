<?php

namespace NSRosenqvist\ApiToolkit\Middleware;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CacheMiddleware
{
    /**
     * Response cache store
     *
     * @var array
     */
    protected $cache = [];

    /**
     * Next middleware handler
     *
     * @var callable
     */
    protected $nextHandler;

    /**
     * HTTP requests we can cache
     *
     * @var array
     */
    protected const CACHEABLE = [
        'GET' => true,
        'HEAD' => true,
        'OPTIONS' => true,
        'TRACE' => true
    ];

    /**
     * @param callable
     */
    public function __construct(callable $nextHandler)
    {
        $this->nextHandler = $nextHandler;
    }

    /**
     * Generate the request id
     *
     * @param RequestInterface $request
     * @return string
     */
    protected function getRequestId(RequestInterface $request): string
    {
        // Ensure that the query is always ordered the same
        extract(parse_url($request->getRequestTarget()));
        parse_str($query ?? '', $query);
        ksort($query);

        // Build identifier
        $method = strtoupper($request->getMethod());
        $query = http_build_query($query);

        return  $method . ' ' . $path . (! empty($query) ? '?' . $query : '');
    }

    /**
     * Get cached response if one exists
     *
     * @param RequestInterface $request
     * @return ResponseInterface|null
     */
    protected function getCachedResponse(RequestInterface $request): ?ResponseInterface
    {
        if (empty($this->cache)) {
            return null;
        }

        $id = $this->getRequestId($request);

        foreach ($this->cache as $cache) {
            if ($id === $this->getRequestId($cache['request'])) {
                return $cache['response'];
            }
        }

        return null;
    }

    /**
     * Middleware main function
     *
     * @param RequestInterface $request
     * @param array $options
     * @return PromiseInterface
     */
    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        // Skip handling the request cache if middleware is disabled
        if (isset($options['cache']) && ! $options['cache']) {
            return ($this->nextHandler)($request, $options);
        }

        $cache = &$this->cache;
        $method = strtoupper($request->getMethod());

        if (! isset(self::CACHEABLE[$method])) {
            return ($this->nextHandler)($request, $options);
        }

        if ($response = $this->getCachedResponse($request)) {
            return $response instanceof \Throwable
                ? Promise\Create::rejectionFor($response)
                : Promise\Create::promiseFor($response);
        }

        return ($this->nextHandler)($request, $options)->then(
            function (?ResponseInterface $response) use ($request, &$cache, $options) {
                $cache[] = [
                    'request'  => $request,
                    'response' => $response,
                    'error'    => null,
                    'options'  => $options
                ];
                return $response;
            },
            function (RequestException $reason) use ($request, &$cache, $options) {
                $cache[] = [
                    'request'  => $request,
                    'response' => $reason->getResponse(),
                    'error'    => $reason,
                    'options'  => $options
                ];
                return Promise\Create::rejectionFor($reason);
            }
        );
    }
}
