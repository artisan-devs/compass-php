<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\promoted_properties\passing;

abstract class AbstractWithConstructor
{
    private string $name;

    abstract public function __construct(string $name);
}
