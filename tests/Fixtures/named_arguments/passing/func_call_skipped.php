<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\named_arguments\passing;

final class FuncCallSkipped
{
    public function run(array $items): int
    {
        // Plain function calls are intentionally out of scope for NamedArgumentsRule.
        return count($items) + strlen('hello');
    }
}
