<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\named_method_arguments\failing;

final class MethodCallPositional
{
    public function run(\DateTimeImmutable $clock): \DateTimeImmutable
    {
        return $clock->modify('+1 day');
    }
}
