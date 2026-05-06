<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\named_method_arguments\passing;

final class Variadic
{
    public function run(\ArrayObject $obj, array $args): void
    {
        $obj->setFlags(...$args);
    }
}
