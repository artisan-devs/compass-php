<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\constructor_property_promotion\passing;

final class AlreadyPromoted
{
    public function __construct(
        private readonly string $name,
        private readonly int $count,
    ) {}
}
