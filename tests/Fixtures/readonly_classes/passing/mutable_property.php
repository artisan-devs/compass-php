<?php

declare(strict_types=1);

final class MutableProperty
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
