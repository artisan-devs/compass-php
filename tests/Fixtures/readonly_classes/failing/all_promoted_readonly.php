<?php

declare(strict_types=1);

final class AllPromotedReadonly
{
    public function __construct(
        public readonly string $value,
        public readonly string $prefix,
    ) {
    }
}
