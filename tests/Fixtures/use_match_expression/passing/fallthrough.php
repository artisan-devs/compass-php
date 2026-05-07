<?php

declare(strict_types=1);

function classify(string $x): string
{
    $label = '';
    switch ($x) {
        case 'a':
        case 'b':
            doSomething();
        case 'c':
            $label = 'c';
            break;
    }

    return $label;
}

function doSomething(): void {}
