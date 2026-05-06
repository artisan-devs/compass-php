<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\named_method_arguments\passing;

final class FuncCallSkipped
{
    public function run(array $items): int
    {
        // FuncCall is intentionally out of scope for NamedMethodArgumentsRule.
        return count($items) + strlen('hello');
    }
}
