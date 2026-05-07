<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\named_arguments\failing;

final class NullsafePositional
{
    public function run(?\DateTimeImmutable $clock): ?\DateTimeImmutable
    {
        return $clock?->modify('+1 day');
    }
}
