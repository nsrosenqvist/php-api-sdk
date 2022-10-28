<?php

namespace NSRosenqvist\APIcly;

use ArrayAccess;
use IteratorAggregate;
use Traversable;
use Countable;

class ListData implements ArrayAccess, IteratorAggregate, Countable
{
    use Macroable;
    
    protected $data = [];

    /**
     * Create a new configuration repository.
     *
     * @param  array  $items
     * @return void
     */
    public function __construct(array $list = [])
    {
        foreach ($list as $data)
        {
            $this->data[] = new ObjectData($data);
        }
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function add(ObjectData $data): void
    {
        $this->data[] = $data;
    }
 
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    public function offsetGet($offset): ObjectData
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }
}
