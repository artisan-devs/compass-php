<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\named_method_arguments\passing;

class Base
{
    public function __construct(public string $name) {}
}

final class Child extends Base
{
    public function __construct()
    {
        parent::__construct('positional but covered by NamedArgumentsRule, not this rule');
    }
}
