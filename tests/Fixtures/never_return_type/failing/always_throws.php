<?php

declare(strict_types=1);

function fail(string $msg): void
{
    throw new RuntimeException($msg);
}

function panicByCode(int $code): int
{
    if ($code === 0) {
        throw new InvalidArgumentException('zero');
    } else {
        exit($code);
    }
}
