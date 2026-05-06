<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\promoted_properties\passing;

final class AlreadyPromoted
{
    public function __construct(
        private readonly string $name,
        private readonly int $count,
    ) {}
}
