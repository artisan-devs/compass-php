<?php

declare(strict_types=1);

final class ConstructorNoReturn
{
    public function __construct(private readonly string $name)
    {
    }
}
