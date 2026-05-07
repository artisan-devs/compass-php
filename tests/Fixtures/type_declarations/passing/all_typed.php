<?php

declare(strict_types=1);

final class AllTyped
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function greet(string $other): string
    {
        return $this->name.' meets '.$other;
    }
}
