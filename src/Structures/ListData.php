<?php

namespace NSRosenqvist\ApiToolkit\Structures;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Traits\Macroable;
use IteratorAggregate;
use NSRosenqvist\ApiToolkit\Structures\ObjectData;
use Traversable;                                   // phpcs:ignore
use function NSRosenqvist\ApiToolkit\array_map_recursive; // phpcs:ignore

class ListData implements ArrayAccess, IteratorAggregate, Countable, Jsonable, Arrayable
{
    use Macroable;

    /**
     * Data store
     *
     * @var array
     */
    protected $data = [];

    /**
     * @param  array $items
     * @return void
     */
    public function __construct(array $list = [])
    {
        foreach ($list as $data) {
            if (is_iterable($data)) {
                $this->data[] = new ObjectData($data);
            } else {
                $this->data[] = $data;
            }
        }
    }

    /**
     * @return integer
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $data = array_map_recursive(function ($item) {
            if ($item instanceof Arrayable) {
                return $item->toArray();
            } else {
                return $item;
            }
        }, $this->data);

        return $data;
    }

    /**
     * @return array
     */
    public function toJson($options = 0)
    {
        $data = array_map_recursive(function ($item) {
            if ($item instanceof Arrayable) {
                return $item->toArray();
            } else {
                return $item;
            }
        }, $this->data);

        return json_encode($data, $options);
    }

    /**
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset
     * @return boolean
     */
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }
}
