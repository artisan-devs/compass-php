<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\promoted_properties\passing;

final class Renamed
{
    private string $displayName;

    public function __construct(string $name)
    {
        $this->displayName = $name;
    }
}
