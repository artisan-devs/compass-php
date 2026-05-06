<?php

declare(strict_types=1);

namespace Sidetours\Compass\Engine;

final readonly class Violation
{
    public function __construct(
        public string $rule,
        public string $message,
        public string $file,
        public int $line,
    ) {
    }

    public function fingerprint(): string
    {
        return sha1($this->rule.'|'.$this->file.'|'.$this->line.'|'.$this->message);
    }

    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
        ];
    }
}
