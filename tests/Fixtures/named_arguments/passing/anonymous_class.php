<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\named_arguments\passing;

final class AnonymousClassConsumer
{
    public function build(): object
    {
        return new class('positional ok in anonymous class') {
            public function __construct(public string $note) {}
        };
    }
}
