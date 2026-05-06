<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\named_arguments\failing;

final class Positional
{
    public function make(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now');
    }
}
