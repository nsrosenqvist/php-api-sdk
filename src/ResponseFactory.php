<?php

namespace NSRosenqvist\ApiToolkit;

use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Support\Jsonable;
use NSRosenqvist\ApiToolkit\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class ResponseFactory implements ResponseFactoryInterface
{
    /**
     * Default stubs directory
     *
     * @var string
     */
    protected $dir = '';

    /**
     * @param string $dir Default stubs directory
     */
    public function __construct(string $dir = '')
    {
        $this->setDefaultContentDirectory($dir);
    }

    /**
     * Set default stubs directory
     *
     * @param string|null $dir
     * @return self
     */
    public function setDefaultContentDirectory(?string $dir): self
    {
        if (! empty($dir) && ! @is_dir($dir)) {
            throw new \InvalidArgumentException('Resource directory, when set, must exist and be accessible');
        }

        $this->dir = $dir ?: '';

        return $this;
    }

    /**
     * Create a response
     *
     * @param int $code
     * @param string $content
     * @param array $headers
     * @param string $version
     * @param string|null $reason
     * @param string|null $dir
     * @return ResponseInterface
     */
    public function create(
        int $code,
        $content = '',
        array $headers = [],
        string $version = '1.1',
        ?string $reason = null,
        ?string $dir = null
    ): ResponseInterface {
        // Verify stubs directory
        $dir = $dir ?? $this->dir;

        if (! empty($dir) && ! @is_dir($dir)) {
            throw new \InvalidArgumentException('Resource directory, when set, must exist and be accessible');
        }

        // Load and set response body
        if (! empty($content)) {
            // If $content contains a path to a file we load its contents
            if (is_string($content)) {
                if (@file_exists($path = $content) || @file_exists($path = $dir . '/' . $content)) {
                    $content = file_get_contents($path);

                    if (! isset($headers['Content-Type'])) {
                        $headers['Content-Type'] = mime_content_type($path) ?: null;
                    }
                }
            // If the content is an object, we encode it as JSON
            } elseif (is_object($content)) {
                if ($content instanceof Jsonable) {
                    $content = $content->toJson(JSON_PRETTY_PRINT);
                } else {
                    $content = json_encode($content, JSON_PRETTY_PRINT);
                }

                if (! isset($headers['Content-Type'])) {
                    $headers['Content-Type'] = 'application/json';
                }
            }
        }

        return new Response($code, $headers, $content, $version, $reason);
    }

    /**
     * Get default response
     *
     * @return ResponseInterface
     */
    public function default(): ResponseInterface
    {
        return $this->create(200);
    }
}
