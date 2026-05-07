<?php

declare(strict_types=1);

function panic(string $msg): never
{
    throw new RuntimeException($msg);
}

function maybe(int $x): int
{
    if ($x < 0) {
        throw new InvalidArgumentException('negative');
    }

    return $x * 2;
}

function noReturnType(string $msg)
{
    throw new RuntimeException($msg);
}
