<?php

namespace NSRosenqvist\ApiToolkit\Parsers;

use Symfony\Component\Yaml\Yaml as YamlParser;
use NSRosenqvist\ApiToolkit\Parsers\ParserInterface;

class Yaml implements ParserInterface
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
            $data = YamlParser::parseFile($path, YamlParser::PARSE_CONSTANT | YamlParser::PARSE_OBJECT_FOR_MAP);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Failed to parse Yaml file: ' . $e->getMessage());
        }

        return $data;
    }
}
