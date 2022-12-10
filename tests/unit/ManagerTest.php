<?php

use NSRosenqvist\ApiToolkit\ADK;
use NSRosenqvist\ApiToolkit\Tests\InspectableManager;

it('can register drivers', function () {
    $api = new InspectableManager();
    $api->registerDriver('test-1', '\\NSRosenqvist\\ApiToolkit\\Tests\\DriverImplementation');
    $api->registerDriver('test-2', '\\NSRosenqvist\\ApiToolkit\\Tests\\DriverImplementation');
    $this->assertEquals(2, count($api->inspect('drivers')));
});

it('can initialize drivers', function () {
    $api = new InspectableManager();
    $api->registerDriver('test', '\\NSRosenqvist\\ApiToolkit\\Tests\\DriverImplementation');
    $this->assertTrue($api->get('test') instanceof ADK);
});
