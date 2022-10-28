<?php

namespace NSRosenqvist\APIcly;

class Chain {
    protected $request;
    protected array $links = [];

    public function __construct(PreparedRequest $request, array $links = [])
    {
        $this->request = $request;

        foreach ($links as $link) {
            $this->{$link};
        }
    }

    public function __get($property): self
    {
        $this->links[] = $property;

        return $this;
    }

    public function __call(string $method, array $arguments = []): PreparedRequest
    {
        return call_user_func_array([$this->request->chain($this), $method], $arguments);
    }

    public function __toString(): string
    {
        return implode('/', $this->links);
    }

}