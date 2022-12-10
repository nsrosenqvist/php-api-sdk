<?php

namespace NSRosenqvist\ApiToolkit\Parsers;

use Ahc\Json\Comment;
use NSRosenqvist\ApiToolkit\Parsers\ParserInterface;

class Jsonc implements ParserInterface
{
    /**
     * Parse the specified file
     *
     * @param string $path
     * @return void
     */
    public function parse(string $path)
    {
        $contents = file_get_contents($path);
        $contents = (new Comment())->strip($contents);
        $data = json_decode($contents);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Failed to parse JsonC file: ' . json_last_error_msg());
        }

        return $data;
    }
}
