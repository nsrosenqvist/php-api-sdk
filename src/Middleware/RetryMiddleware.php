<?php

/**
 * Substantial portions of the code for this middleware is based on the retry
 * middleware included in Guzzle 7. Please review its license here below:
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2011 Michael Dowling <mtdowling@gmail.com>
 * Copyright (c) 2012 Jeremy Lindblom <jeremeamia@gmail.com>
 * Copyright (c) 2014 Graham Campbell <hello@gjcampbell.co.uk>
 * Copyright (c) 2015 Márk Sági-Kazár <mark.sagikazar@gmail.com>
 * Copyright (c) 2015 Tobias Schultze <webmaster@tubo-world.de>
 * Copyright (c) 2016 Tobias Nyholm <tobias.nyholm@gmail.com>
 * Copyright (c) 2016 George Mponos <gmponos@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @see https://raw.githubusercontent.com/guzzle/guzzle/7.5.0/LICENSE
 */

namespace NSRosenqvist\ApiToolkit\Middleware;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware that retries requests based on the boolean result of
 * invoking the provided "decider" function.
 *
 * @final
 */
class RetryMiddleware
{
    /**
     * Next handler
     *
     * @var callable
     */
    protected $nextHandler;

    /**
     * Custom callback for deciding whether we should reattempt request
     *
     * @var callable
     */
    protected $decider;

    /**
     * Function for determining how long delay there should be until next attempt
     *
     * @var callable
     */
    protected $delay;

    /**
     * @param callable $decider      Function that accepts the number of retries,
     *                               a request, [response], and [exception] and
     *                               returns true if the request is to be retried.
     * @param callable $nextHandler  Next handler to invoke.
     * @param callable|null $delay   Function that accepts the number of retries
     *                               and returns the number of milliseconds to delay.
     */
    public function __construct(callable $nextHandler, ?callable $decider = null, ?callable $delay = null)
    {
        $this->nextHandler = $nextHandler;
        $this->decider = $decider;
        $this->delay = $delay ?: [__CLASS__, 'exponentialDelay'];
    }

    /**
     * Default exponential backoff delay function.
     *
     * @param int $retries
     * @return int milliseconds.
     */
    public static function exponentialDelay(int $retries): int
    {
        return (int) pow(2, $retries - 1) * 1000;
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
        if (!isset($options['retries'])) {
            $options['retries'] = 0;
        }

        $fn = $this->nextHandler;
        return $fn($request, $options)
            ->then(
                $this->onFulfilled($request, $options),
                $this->onRejected($request, $options)
            );
    }

    /**
     * Default decider function
     *
     * @param array $options
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     * @param RequestException|null $exception
     * @return boolean
     */
    protected function decide(
        array $options,
        RequestInterface $request,
        ?ResponseInterface $response,
        ?RequestException $exception
    ): bool {
        if (is_callable($this->decider)) {
            return ($this->decider)($options, $request, $response, $exception);
        }

        return $options['retries'] < ($options['max_retries'] ?? 0)
            && ! is_null($response)
            && 429 === $response->getStatusCode();
    }

    /**
     * Execute fulfilled closure
     *
     * @param RequestInterface $request
     * @param array $options
     * @return callable
     */
    protected function onFulfilled(RequestInterface $request, array $options): callable
    {
        return function (?ResponseInterface $response) use ($request, $options) {
            if (! $this->decide($options, $request, $response, null)) {
                return $response;
            }

            return $this->doRetry($request, $options, $response);
        };
    }

    /**
     * Execute rejected closure
     *
     * @param RequestInterface $request
     * @param array $options
     * @return callable
     */
    protected function onRejected(RequestInterface $request, array $options): callable
    {
        return function (RequestException $reason) use ($request, $options) {
            if (! $this->decide($options, $request, null, $reason)) {
                return Promise\Create::rejectionFor($reason);
            }
            return $this->doRetry($request, $options);
        };
    }

    /**
     * Perform reattempt
     *
     * @param RequestInterface $request
     * @param array $options
     * @param ResponseInterface|null $response
     * @return PromiseInterface
     */
    protected function doRetry(
        RequestInterface $request,
        array $options,
        ResponseInterface $response = null
    ): PromiseInterface {
        $options['delay'] = ($this->delay)(++$options['retries'], $response, $request);

        return $this($request, $options);
    }
}
