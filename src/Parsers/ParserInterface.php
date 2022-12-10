<?php

namespace NSRosenqvist\ApiToolkit\Parsers;

interface ParserInterface
{
    /**
     * Parse the specified file
     *
     * @param string $path
     * @return void
     */
    public function parse(string $path);
}
