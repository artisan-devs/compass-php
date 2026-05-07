<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\constructor_property_promotion\passing;

abstract class AbstractWithConstructor
{
    private string $name;

    abstract public function __construct(string $name);
}
