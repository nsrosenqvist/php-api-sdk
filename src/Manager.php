<?php

namespace NSRosenqvist\ApiToolkit;

use NSRosenqvist\ApiToolkit\ADK;

class Manager
{
    /**
     * Driver store
     *
     * @var array
     */
    protected $drivers = [];

    /**
     * Register a driver
     *
     * @param string $id
     * @param string|ADK $driver
     * @return self
     */
    public function registerDriver(string $id, $driver): self
    {
        if (! is_string($driver) && ! $driver instanceof ADK) {
            throw new \InvalidArgumentException("Driver with $id is not a valid API driver");
        }

        $this->drivers[$id] = $driver;

        return $this;
    }

    /**
     * Retrieve and instantiate a driver
     *
     * @param string $id
     * @return ADK
     */
    public function get(string $id): ADK
    {
        if (is_string($class = $this->drivers[$id])) {
            $this->drivers[$id] = new $class();
        }

        return $this->drivers[$id];
    }
}
