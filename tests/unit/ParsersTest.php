<?php

use NSRosenqvist\ApiToolkit\Parsers\Json;
use NSRosenqvist\ApiToolkit\Parsers\Jsonc;
use NSRosenqvist\ApiToolkit\Parsers\Php;
use NSRosenqvist\ApiToolkit\Parsers\Yaml;       // phpcs:ignore
use function NSRosenqvist\ApiToolkit\to_object; // phpcs:ignore

it('can parse Json manifests', function () {
    $this->assertEquals(
        to_object([
            'format/json' => [
                'code' => 201,
            ],
        ]),
        (new Json())->parse(MANIFESTS_ROOT . '/format.json')
    );
});

it('can parse JsonC manifests', function () {
    $this->assertEquals(
        to_object([
            'format/jsonc' => [
                'code' => 201,
                'content' => 'body',
            ],
        ]),
        (new Jsonc())->parse(MANIFESTS_ROOT . '/format.jsonc')
    );
});

it('can parse Yaml manifests', function () {
    $this->assertEquals(
        to_object([
            'format/yml' => [
                'code' => 201,
            ],
        ]),
        (new Yaml())->parse(MANIFESTS_ROOT . '/format.yml')
    );
});

it('can parse PHP manifests', function () {
    $this->assertEquals(
        to_object([
            'format/php' => [
                'code' => 201,
            ],
        ]),
        (new Php())->parse(MANIFESTS_ROOT . '/format.php')
    );
});
