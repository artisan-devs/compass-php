<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\named_arguments\passing;

final class NoArgs
{
    public function run(\DateTimeImmutable $clock): string
    {
        return $clock->format(format: 'Y-m-d');
    }
}
