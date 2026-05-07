<?php

declare(strict_types=1);

function classify(string $x): string
{
    switch ($x) {
        case 'a':
            return 'A';
        case 'b':
            return 'B';
        default:
            return 'other';
    }
}
