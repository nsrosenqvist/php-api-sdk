<?php

namespace NSRosenqvist\ApiToolkit;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use NSRosenqvist\ApiToolkit\ADK;
use NSRosenqvist\ApiToolkit\Middleware\CacheMiddleware;
use NSRosenqvist\ApiToolkit\Middleware\MockMiddleware;
use NSRosenqvist\ApiToolkit\Middleware\RecorderMiddleware;
use NSRosenqvist\ApiToolkit\Middleware\RetryMiddleware;
use NSRosenqvist\ApiToolkit\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class StandardApi extends ADK
{
    /**
     * Response factory
     *
     * @var ResponseFactoryInterface
     */
    protected $factory;

    /**
     * Response queue
     *
     * @var array<ResponseInterface>
     */
    protected $queue = [];

    /**
     * Default middleware config
     *
     * @var array
     */
    protected $config = [
        'cache' => false,
        'max_retries' => 0,
        'mock' => false,
        'manifest' => null,
        'stubs' => null,
    ];

    /**
     * @param array $clientOptions
     * @param array $requestDefaults
     * @param ResponseFactoryInterface|null $factory
     */
    public function __construct(
        array $clientOptions = [],
        array $requestDefaults = [],
        ?ResponseFactoryInterface $factory = null
    ) {
        parent::__construct($clientOptions, array_merge($this->config, $requestDefaults));

        $this->factory = $factory ?? new ResponseFactory();
    }

    /**
     * Set default manifest option
     *
     * @param string|array|object $manifest
     * @return self
     */
    public function setDefaultManifest($manifest): self
    {
        $this->requestDefaults['manifest'] = $manifest;

        return $this;
    }

    /**
     * Set default caching option
     *
     * @param boolean $cache
     * @return self
     */
    public function setDefaultCaching(bool $cache): self
    {
        $this->requestDefaults['cache'] = $cache;

        return $this;
    }

    /**
     * Set default max_retries option
     *
     * @param integer $retries
     * @return self
     */
    public function setDefaultRetries(int $retries): self
    {
        $this->requestDefaults['max_retries'] = $retries;

        return $this;
    }

    /**
     * Set default stubs option
     *
     * @param string|null $dir
     * @return self
     */
    public function setDefaultContentDirectory(?string $dir): self
    {
        $this->requestDefaults['stubs'] = $dir;

        return $this;
    }

    /**
     * Toggle mocking globally
     *
     * @param bool $mock Whether mocking should be enabled or not
     * @return $this
     */
    public function mocking(bool $mock = true): self
    {
        $this->requestDefaults['mock'] = $mock;

        return $this;
    }

    /**
     * Get the client's handler stack
     *
     * @return HandlerStack
     */
    protected function handlerStack(): HandlerStack
    {
        $stack = parent::handlerStack();

        $stack->push($this->getRecorderHandler());
        $stack->push($this->getMockHandler());
        $stack->push($this->getCacheHandler());
        $stack->push($this->getRetryHandler());

        return $stack;
    }

    /**
     * Get recorder middleware
     *
     * @return callable
     */
    protected function getRecorderHandler(): callable
    {
        return function (callable $handler): RecorderMiddleware {
            return new RecorderMiddleware($handler);
        };
    }

    /**
     * Get mock middleware
     *
     * @return callable
     */
    protected function getMockHandler(): callable
    {
        return function (callable $handler): MockMiddleware {
            return new MockMiddleware($handler, $this->factory);
        };
    }

    /**
     * Get cache middleware
     *
     * @return callable
     */
    protected function getCacheHandler(): callable
    {
        return function (callable $handler): CacheMiddleware {
            return new CacheMiddleware($handler);
        };
    }

    /**
     * Get retry middleware
     *
     * @return callable
     */
    protected function getRetryHandler(): callable
    {
        return function (callable $handler): RetryMiddleware {
            return new RetryMiddleware($handler);
        };
    }

    /**
     * Create a response
     *
     * @param array|int $code
     * @param string $content
     * @param array $headers
     * @param string $version
     * @param string|null $reason
     * @return ResponseInterface
     */
    public function response(
        $code,
        $content = '',
        array $headers = [],
        string $version = '1.1',
        ?string $reason = null
    ): ResponseInterface {
        // Support providing data as an associative array
        if (is_array($code)) {
            extract($code);
        }

        return $this->factory->create(
            $code,
            $content,
            $headers,
            $version,
            $reason,
            $this->requestDefaults['stubs'] ?? null
        );
    }

    /**
     * Queue mocking response(s)
     *
     * @param ResponseInterface|array<ResponseInterface> $response
     * @return self
     */
    public function queue($response): self
    {
        $response = (is_array($response)) ? $response : [$response];
        $this->queue = array_merge($this->queue, $response);

        return $this;
    }

    /**
     * Toggle caching for the initated request chain
     *
     * @param bool $cache Whether responses should be cached or not
     * @return RequestChain
     */
    public function cache(bool $cache = true): RequestChain
    {
        return $this->chain([
            'cache' => $cache,
        ]);
    }

    /**
     * Process options before executing request
     *
     * @param array $options
     * @return array
     */
    protected function processOptions(array $options): array
    {
        // If a queue exists, we populate mock responses from beginning of the queue
        // and force enable the mocking middleware
        if (! isset($options['response']) && ! empty($this->queue)) {
            $options['response'] = array_shift($this->queue) ?: null;
            $options['mock'] = true;
        }

        return $options;
    }
}
