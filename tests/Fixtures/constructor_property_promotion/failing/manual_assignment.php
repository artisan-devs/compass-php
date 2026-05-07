<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\constructor_property_promotion\failing;

final class ManualAssignment
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
