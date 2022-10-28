<?php

namespace NSRosenqvist\APIcly;

use GuzzleHttp\Psr7\Response;
use ArrayAccess;
use Countable;
use Psr\Http\Message\ResponseInterface;

class Result implements Countable, ResponseInterface {
    public $response;
    public $success = false;
    public $code = 0;
    public $count = 0;
    public $data;

    public function __construct(Response $response, $data = null)
    {
        $this->response = $response;
        $this->code = $response->getStatusCode();
        $this->data = $data;
        $this->count = $this->count();

        if ($this->code >= 200 && $this->code < 300) {
            $this->success = true;
        }
    }

    public function count(): int
    {
        if ($this->data instanceof Countable) {
            return count($this->data);
        } elseif (! empty($this->data)) {
            return 1;
        }

        return 0;
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function getReasonPhrase(): string
    {
        return $this->response->getReasonPhrase();
    }

    public function withStatus($code, $reasonPhrase = ''): ResponseInterface
    {
        $response = $this->response->withStatus($code, $reasonPhrase);

        return self::__construct($response, $this->data);
    }
}