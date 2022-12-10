<?php

/**
 * Substantial portions of the code for this middleware is based on the repository
 * class included illuminate/support. Please review its license here below:
 *
 * The MIT License (MIT)
 *
 * Copyright (c) Taylor Otwell
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
 * @see https://raw.githubusercontent.com/illuminate/support/master/LICENSE.md
 */

namespace NSRosenqvist\ApiToolkit\Structures;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Macroable;           // phpcs:ignore
use function NSRosenqvist\ApiToolkit\array_map_recursive; // phpcs:ignore

class ObjectData implements ArrayAccess, Jsonable, Arrayable
{
    use Macroable;

    /**
     * The object properties store
     *
     * @var array
     */
    protected $properties = [];

    /**
     * @param  iterable|stdClass $properties
     * @return void
     */
    public function __construct($properties = [])
    {
        foreach ($properties as $key => $value) {
            $this->properties[$key] = $value;
        }
    }

    /**
     * Determine if the given property value exists.
     *
     * @param  string  $key
     * @return bool
     */
    public function has($key)
    {
        return Arr::has($this->properties, $key);
    }

    /**
     * Get the specified property value.
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null): mixed
    {
        if (is_array($key)) {
            return $this->getMany($key);
        }

        return Arr::get($this->properties, $key, $default);
    }

    /**
     * Get many property values.
     *
     * @param  array  $keys
     * @return array
     */
    public function getMany($keys)
    {
        $config = [];

        foreach ($keys as $key => $default) {
            if (is_numeric($key)) {
                [$key, $default] = [$default, null];
            }

            $config[$key] = Arr::get($this->properties, $key, $default);
        }

        return $config;
    }

    /**
     * Set a given property value.
     *
     * @param  array|string  $key
     * @param  mixed  $value
     * @return void
     */
    public function set($key, $value = null)
    {
        $keys = is_array($key) ? $key : [$key => $value];

        foreach ($keys as $key => $value) {
            Arr::set($this->properties, $key, $value);
        }
    }

    /**
     * Get all of the object properties as an array
     *
     * @return array
     */
    public function toArray()
    {
        $properties = array_map_recursive(function ($item) {
            if ($item instanceof Arrayable) {
                return $item->toArray();
            } else {
                return $item;
            }
        }, $this->properties);

        return $properties;
    }

    /**
     * Get all of the object properties as encoded JSON
     *
     * @return array
     */
    public function toJson($options = 0)
    {
        $properties = array_map_recursive(function ($item) {
            if ($item instanceof Arrayable) {
                return $item->toArray();
            } else {
                return $item;
            }
        }, $this->properties);

        return json_encode((object) $properties, $options);
    }

    /**
     * Determine if the given property exists.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return $this->has($key);
    }

    /**
     * Get a property.
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function offsetGet($key): mixed
    {
        return $this->get($key);
    }

    /**
     * Set a property.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Unset a property.
     *
     * @param  mixed  $key
     * @return void
     */
    public function offsetUnset($key): void
    {
        $this->set($key, null);
    }

    /**
     * @param string $key
     * @return void
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function __set(string $key, $value)
    {
        return $this->set($key, $value);
    }

    /**
     * @param string $key
     * @return boolean
     */
    public function __isset(string $key)
    {
        return $this->has($key);
    }
}
