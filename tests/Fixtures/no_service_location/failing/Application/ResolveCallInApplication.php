<?php

declare(strict_types=1);

final class ResolveCallInApplication
{
    public function get(): \stdClass
    {
        return resolve(\stdClass::class);
    }
}
