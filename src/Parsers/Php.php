<?php

namespace NSRosenqvist\ApiToolkit\Parsers;

use Illuminate\Contracts\Support\Arrayable;
use NSRosenqvist\ApiToolkit\Parsers\ParserInterface;      // phpcs:ignore
use function NSRosenqvist\ApiToolkit\array_map_recursive; // phpcs:ignore
use function NSRosenqvist\ApiToolkit\to_object;           // phpcs:ignore

class Php implements ParserInterface
{
    /**
     * Parse the specified file
     *
     * @param string $path
     * @return void
     */
    public function parse(string $path)
    {
        try {
            $data = require $path;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Failed to parse PHP file: ' . $e->getMessage());
        }

        // Convert arrayable objects
        $data = array_map_recursive(function ($item) {
            if ($item instanceof Arrayable) {
                return $item->toArray();
            }
            return $item;
        }, $data);

        // Convert associative arrays to objects
        $data = to_object($data);

        return $data;
    }
}
