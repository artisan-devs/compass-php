<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\promoted_properties\passing;

final class Transformed
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = strtolower($name);
    }
}
