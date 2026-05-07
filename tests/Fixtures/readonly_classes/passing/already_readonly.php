<?php

declare(strict_types=1);

final readonly class AlreadyReadonly
{
    public function __construct(
        public string $value,
    ) {
    }
}
