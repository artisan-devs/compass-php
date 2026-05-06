<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\named_method_arguments\failing;

final class StaticPositional
{
    public function run(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d', '2026-01-01');
    }
}
