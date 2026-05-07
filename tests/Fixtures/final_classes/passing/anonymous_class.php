<?php

declare(strict_types=1);

$x = new class {
    public function ping(): string
    {
        return 'pong';
    }
};
