<?php

namespace NSRosenqvist\ApiToolkit\Tests;

trait InspectableProperties
{
    public function inspect(string $property)
    {
        return $this->{$property};
    }
}
