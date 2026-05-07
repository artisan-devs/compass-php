<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class UseStrContainsRule implements Rule
{
    public function name(): string
    {
        return 'use-str-contains';
    }

    public function shortDescription(): string
    {
        return 'Use str_contains/str_starts_with/str_ends_with instead of strpos comparisons.';
    }

    public function nodeTypes(): array
    {
        return [Expr\BinaryOp\Identical::class, Expr\BinaryOp\NotIdentical::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        if (! ($node instanceof Expr\BinaryOp\Identical || $node instanceof Expr\BinaryOp\NotIdentical)) {
            return [];
        }

        [$call, $literal] = $this->extractCallAndLiteral($node);
        if ($call === null || ! $call->name instanceof Node\Name) {
            return [];
        }

        $fn = $call->name->toLowerString();
        if ($fn !== 'strpos') {
            return [];
        }

        $suggestion = null;
        if ($literal instanceof Expr\ConstFetch && $literal->name->toLowerString() === 'false') {
            $suggestion = $node instanceof Expr\BinaryOp\NotIdentical ? 'str_contains(...)' : '!str_contains(...)';
        } elseif ($literal instanceof Scalar\Int_ && $literal->value === 0 && $node instanceof Expr\BinaryOp\Identical) {
            $suggestion = 'str_starts_with(...)';
        }

        if ($suggestion === null) {
            return [];
        }

        return [new Violation(
            file: $context->file,
            line: $node->getLine(),
            rule: $this->name(),
            message: sprintf('Replace strpos() comparison with %s.', $suggestion),
        )];
    }

    /**
     * Returns [FuncCall, otherSide] when one side of the binary op is a strpos-like FuncCall, otherwise [null, null].
     *
     * @return array{0: ?Expr\FuncCall, 1: ?Node\Expr}
     */
    private function extractCallAndLiteral(Expr\BinaryOp $node): array
    {
        if ($node->left instanceof Expr\FuncCall) {
            return [$node->left, $node->right];
        }
        if ($node->right instanceof Expr\FuncCall) {
            return [$node->right, $node->left];
        }

        return [null, null];
    }

    public function fixPrompt(): string
    {
        return (string) file_get_contents(__DIR__.'/prompts/use-str-contains.md');
    }
}
