<?php

declare(strict_types=1);

function classify(int $x): string
{
    if ($x < 0) {
        return 'negative';
    } else {
        return 'non-negative';
    }
}
