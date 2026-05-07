<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class UseMatchExpressionRule implements Rule
{
    public function name(): string
    {
        return 'use-match-expression';
    }

    public function shortDescription(): string
    {
        return 'Switch statements that map a value to a result (no fall-through, every case terminates) should use a match expression.';
    }

    public function nodeTypes(): array
    {
        return [Stmt\Switch_::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        if (! $node instanceof Stmt\Switch_) {
            return [];
        }
        if ($node->cases === []) {
            return [];
        }

        foreach ($node->cases as $case) {
            if ($case->stmts === []) {
                return [];
            }
            $last = $case->stmts[count($case->stmts) - 1];
            if (! $this->isCleanTerminator($last)) {
                return [];
            }
        }

        return [new Violation(
            file: $context->file,
            line: $node->getLine(),
            rule: $this->name(),
            message: 'Switch statement maps a value to a result with no fall-through; rewrite as a match expression for stricter, comma-grouped, expression-friendly form.',
        )];
    }

    private function isCleanTerminator(Node $stmt): bool
    {
        if ($stmt instanceof Stmt\Break_) {
            return true;
        }
        if ($stmt instanceof Stmt\Return_) {
            return true;
        }
        if ($stmt instanceof Stmt\Continue_) {
            return true;
        }
        if ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\Throw_) {
            return true;
        }

        return false;
    }

    public function fixPrompt(): string
    {
        return (string) file_get_contents(__DIR__.'/prompts/use-match-expression.md');
    }
}
