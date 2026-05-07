<?php

declare(strict_types=1);

function describe(string $s): string
{
    return match ($s) {
        'a' => 'A',
        'b' => 'B',
        default => 'other',
    };
}
