<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\constructor_property_promotion\passing;

final class Renamed
{
    private string $displayName;

    public function __construct(string $name)
    {
        $this->displayName = $name;
    }
}
