<?php

declare(strict_types=1);

final class AppMakeCallInDomain
{
    public function get(): \stdClass
    {
        return \App::make(\stdClass::class);
    }
}
