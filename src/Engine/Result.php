<?php

declare(strict_types=1);

namespace Sidetours\Compass\Engine;

final readonly class Result
{
    /**
     * @param list<Violation> $violations
     * @param list<Violation> $ignored
     * @param list<string>    $errors    Parser/IO errors that prevented analysis of a file.
     */
    public function __construct(
        public array $violations,
        public array $ignored,
        public array $errors,
        public int $filesScanned,
    ) {
    }

    public function clean(): bool
    {
        return $this->violations === [] && $this->errors === [];
    }
}
