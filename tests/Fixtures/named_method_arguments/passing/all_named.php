<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\named_method_arguments\passing;

final class AllNamed
{
    public function run(\DateTimeImmutable $clock, ?\DateTimeImmutable $maybe): void
    {
        $clock->modify(modifier: '+1 day');
        $maybe?->modify(modifier: '+1 day');
        \DateTimeImmutable::createFromFormat(format: 'Y-m-d', datetime: '2026-01-01');
    }
}
