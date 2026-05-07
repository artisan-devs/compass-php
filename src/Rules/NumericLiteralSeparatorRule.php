<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Scalar;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class NumericLiteralSeparatorRule implements Rule
{
    /**
     * Numeric literals at or above this magnitude must use underscore separators.
     */
    private const THRESHOLD = 10000;

    public function name(): string
    {
        return 'numeric-literal-separator';
    }

    public function shortDescription(): string
    {
        return 'Use underscore separators for numeric literals ≥ 10_000 (e.g. 1_000_000 instead of 1000000).';
    }

    public function nodeTypes(): array
    {
        return [Scalar\Int_::class, Scalar\Float_::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        if (! ($node instanceof Scalar\Int_ || $node instanceof Scalar\Float_)) {
            return [];
        }

        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();
        if ($start < 0 || $end < $start) {
            return [];
        }
        $raw = substr($context->source, $start, $end - $start + 1);
        if ($raw === '' || str_contains($raw, '_')) {
            return [];
        }

        $kind = $node->getAttribute('kind');
        if ($node instanceof Scalar\Int_ && $kind !== Scalar\Int_::KIND_DEC) {
            return [];
        }

        $integerPartDigits = $this->countIntegerDigits($raw);
        if ($integerPartDigits < 5) {
            return [];
        }

        if ($node instanceof Scalar\Int_ && abs($node->value) < self::THRESHOLD) {
            return [];
        }
        if ($node instanceof Scalar\Float_ && abs($node->value) < self::THRESHOLD) {
            return [];
        }

        $suggestion = $this->withUnderscores($raw);

        return [new Violation(
            file: $context->file,
            line: $node->getLine(),
            rule: $this->name(),
            message: sprintf('Numeric literal %s should use underscore separators: %s.', $raw, $suggestion),
        )];
    }

    private function countIntegerDigits(string $raw): int
    {
        $sign = (str_starts_with($raw, '-') || str_starts_with($raw, '+')) ? 1 : 0;
        $integerPart = substr($raw, $sign);
        $dot = strpos($integerPart, '.');
        if ($dot !== false) {
            $integerPart = substr($integerPart, 0, $dot);
        }
        $exp = strpos(strtolower($integerPart), 'e');
        if ($exp !== false) {
            $integerPart = substr($integerPart, 0, $exp);
        }

        return strlen($integerPart);
    }

    private function withUnderscores(string $raw): string
    {
        $sign = '';
        if (str_starts_with($raw, '-') || str_starts_with($raw, '+')) {
            $sign = $raw[0];
            $raw = substr($raw, 1);
        }

        $fraction = '';
        $exponent = '';
        $dot = strpos($raw, '.');
        $eIndex = strpos(strtolower($raw), 'e');
        if ($eIndex !== false) {
            $exponent = substr($raw, $eIndex);
            $raw = substr($raw, 0, $eIndex);
        }
        if ($dot !== false && $dot < strlen($raw)) {
            $fraction = substr($raw, $dot);
            $raw = substr($raw, 0, $dot);
        }

        $separated = strrev((string) preg_replace('/(\d{3})(?=\d)/', '$1_', strrev($raw)));

        return $sign.$separated.$fraction.$exponent;
    }

    public function fixPrompt(): string
    {
        return (string) file_get_contents(__DIR__.'/prompts/numeric-literal-separator.md');
    }
}
