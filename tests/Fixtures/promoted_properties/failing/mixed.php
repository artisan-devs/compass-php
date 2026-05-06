<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\promoted_properties\failing;

final class Mixed
{
    private string $name;

    private string $derived;

    public function __construct(string $name, string $other)
    {
        $this->name = $name;
        $this->derived = strtoupper($other);
    }
}
