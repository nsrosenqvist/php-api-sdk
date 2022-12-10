<?php

/**
 * Substantial portions of the code for this middleware is based on the mock
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
use GuzzleHttp\TransferStats;
use NSRosenqvist\ApiToolkit\ResponseFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use NSRosenqvist\ApiToolkit\MockManifest;

class MockMiddleware
{
    /**
     * Id of currently loaded manifest
     *
     * @var string
     */
    protected $manifestId = '';

    /**
     * Manifest data structure
     *
     * @var stdClass
     */
    protected $manifest;

    /**
     * Response factory
     *
     * @var ResponseFactoryInterface
     */
    protected $factory;

    /**
     * Success callback
     *
     * @var callable|null
     */
    protected $onFulfilled;

    /**
     * Failure callback
     *
     * @var callable|null
     */
    protected $onRejected;

    /**
     * Next handler
     *
     * @var callable PromiseInterface
     */
    protected $nextHandler;

    /**
     * @param callable PromiseInterface $nextHandler Next handler to invoke.
     * @param ResponseFactoryInterface $factory
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     */
    public function __construct(
        callable $nextHandler,
        ResponseFactoryInterface $factory,
        callable $onFulfilled = null,
        callable $onRejected = null
    ) {
        $this->nextHandler = $nextHandler;
        $this->factory = $factory;
        $this->onFulfilled = $onFulfilled;
        $this->onRejected = $onRejected;
    }

    /**
     * Retrieve response from manifest
     *
     * @param RequestInterface $request
     * @param string|stdClass $manifest
     * @param string|null $stubsDirectory
     * @return ResponseInterface|null
     */
    public function getManifestResponse(
        RequestInterface $request,
        $manifest,
        ?string $stubsDirectory = null
    ): ?ResponseInterface {
        if (($id = md5($manifest)) === $this->manifestId) {
            $manifest = $this->manifest;
        } else {
            $this->manifestId = $id;
            $this->manifest = new MockManifest($manifest, $this->factory);
            $manifest = $this->manifest;
        }

        return $manifest->match($request, $stubsDirectory);
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
        $response = null;

        // Skip mocking if middleware is disabled
        if (! isset($options['mock']) || ! $options['mock']) {
            return ($this->nextHandler)($request, $options);
        }

        // Process on_headers callbacks
        if (isset($options['on_headers'])) {
            if (! is_callable($options['on_headers'])) {
                throw new \InvalidArgumentException('on_headers must be callable');
            }
            try {
                $options['on_headers']($response);
            } catch (\Exception $e) {
                $msg = 'An error was encountered during the on_headers event';
                $response = new RequestException($msg, $request, $response, $e);
            }
        }

        // Set response from options
        if (is_null($response) && isset($options['response'])) {
            $response = $options['response'] instanceof ResponseInterface
                ? $options['response']
                : null;
        }

        // Set response from manifest
        if (is_null($response) && isset($options['manifest'])) {
            $response = $this->getManifestResponse($request, $options['manifest'], $options['stubs'] ?? null);
            $options['response'] = $response;
        }

        // If no suitable response has been found, return the factory default
        if (is_null($response)) {
            $response = $this->factory->default();
            $options['response'] = $response;
        }

        if (is_callable($response)) {
            $response = $response($request, $options);
        }

        $response = $response instanceof \Throwable
            ? Promise\Create::rejectionFor($response)
            : Promise\Create::promiseFor($response);

        return $response->then(
            function (?ResponseInterface $value) use ($request, $options) {
                $this->invokeStats($request, $options, $value);
                if ($this->onFulfilled) {
                    ($this->onFulfilled)($value);
                }

                if ($value !== null && isset($options['sink'])) {
                    $contents = (string) $value->getBody();
                    $sink = $options['sink'];

                    if (is_resource($sink)) {
                        fwrite($sink, $contents);
                    } elseif (is_string($sink)) {
                        file_put_contents($sink, $contents);
                    } elseif ($sink instanceof StreamInterface) {
                        $sink->write($contents);
                    }
                }

                return $value;
            },
            function (RequestException $reason) use ($request, $options) {
                $this->invokeStats($request, $options, null, $reason);
                if ($this->onRejected) {
                    ($this->onRejected)($reason);
                }
                return Promise\Create::rejectionFor($reason);
            }
        );
    }

    /**
     * Track request stats
     *
     * @param mixed $reason Promise or reason.
     */
    protected function invokeStats(
        RequestInterface $request,
        array $options,
        ResponseInterface $response = null,
        $reason = null
    ): void {
        if (isset($options['on_stats'])) {
            $transferTime = $options['transfer_time'] ?? 0;
            $stats = new TransferStats($request, $response, $transferTime, $reason);
            ($options['on_stats'])($stats);
        }
    }
}
