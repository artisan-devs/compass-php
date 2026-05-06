<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\named_arguments\passing;

final class AllNamed
{
    public function make(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(datetime: 'now');
    }

    public function nested(): array
    {
        return [
            new \DateTimeImmutable(datetime: '2026-01-01'),
            new \DateInterval(duration: 'P1D'),
        ];
    }
}
