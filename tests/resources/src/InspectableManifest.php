<?php

namespace NSRosenqvist\ApiToolkit\Tests;

use NSRosenqvist\ApiToolkit\MockManifest;
use NSRosenqvist\ApiToolkit\Tests\InspectableMethods;
use NSRosenqvist\ApiToolkit\Tests\InspectableProperties;

class InspectableManifest extends MockManifest
{
    use InspectableMethods;
    use InspectableProperties;
}
