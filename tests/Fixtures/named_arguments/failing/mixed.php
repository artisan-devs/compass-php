<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\named_arguments\failing;

final class Mixed
{
    public function make(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', timezone: new \DateTimeZone(timezone: 'UTC'));
    }
}
