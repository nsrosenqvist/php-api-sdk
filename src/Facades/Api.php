<?php

namespace NSRosenqvist\ApiToolkit\Facades;

use Illuminate\Support\Facades\Facade;
use NSRosenqvist\ApiToolkit\Manager;

/**
 * @method \NSRosenqvist\ApiToolkit\Manager registerDriver(string $id, $driver)
 * @method \NSRosenqvist\ApiToolkit\ADK get(string $id)
 *
 * @see \NSRosenqvist\ApiToolkit\Manager;
 */
class Api extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Manager::class;
    }
}
