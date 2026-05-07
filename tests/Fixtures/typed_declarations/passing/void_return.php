<?php

declare(strict_types=1);

final class VoidReturn
{
    public function emit(string $msg): void
    {
        \error_log($msg);
    }
}
