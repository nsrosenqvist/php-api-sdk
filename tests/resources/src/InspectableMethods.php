<?php

namespace NSRosenqvist\ApiToolkit\Tests;

trait InspectableMethods
{
    public function __call(string $method, array $args = [])
    {
        return $this->{$method}(...$args);
    }
}
