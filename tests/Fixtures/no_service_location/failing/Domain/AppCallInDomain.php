<?php

declare(strict_types=1);

final class AppCallInDomain
{
    public function get(): \stdClass
    {
        return app(\stdClass::class);
    }
}
