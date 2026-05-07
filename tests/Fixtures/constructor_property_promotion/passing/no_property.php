<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\constructor_property_promotion\passing;

final class NoProperty
{
    public function __construct(string $name)
    {
        // no property declaration, no $this->name = $name; — just uses the param locally
        $_ = strtolower($name);
    }
}
