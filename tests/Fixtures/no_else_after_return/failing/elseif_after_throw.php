<?php

declare(strict_types=1);

function classify(int $x): string
{
    if ($x < 0) {
        throw new \InvalidArgumentException('negative');
    } elseif ($x === 0) {
        return 'zero';
    } else {
        return 'positive';
    }
}
