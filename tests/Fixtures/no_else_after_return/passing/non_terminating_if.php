<?php

declare(strict_types=1);

function describe(int $x): string
{
    $label = '';
    if ($x > 0) {
        $label = 'positive';
    } else {
        $label = 'non-positive';
    }

    return $label;
}
