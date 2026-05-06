<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\promoted_properties\failing;

final class ManualAssignment
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
