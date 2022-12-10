<?php

namespace NSRosenqvist\ApiToolkit\Tests;

use NSRosenqvist\ApiToolkit\Manager;
use NSRosenqvist\ApiToolkit\Tests\InspectableMethods;
use NSRosenqvist\ApiToolkit\Tests\InspectableProperties;

class InspectableManager extends Manager
{
    use InspectableMethods;
    use InspectableProperties;
}
