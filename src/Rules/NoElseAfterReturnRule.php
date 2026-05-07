<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class NoElseAfterReturnRule implements Rule
{
    public function name(): string
    {
        return 'no-else-after-return';
    }

    public function shortDescription(): string
    {
        return 'Avoid else / elseif when the preceding if branch terminates control flow (return, throw, exit, continue, break).';
    }

    public function nodeTypes(): array
    {
        return [Stmt\If_::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        if (! $node instanceof Stmt\If_) {
            return [];
        }
        if (! $this->lastStatementTerminates($node->stmts)) {
            return [];
        }

        $violations = [];
        foreach ($node->elseifs as $elseif) {
            $violations[] = new Violation(
                file: $context->file,
                line: $elseif->getLine(),
                rule: $this->name(),
                message: 'elseif branch follows an if that always returns/throws/exits — flatten by removing the elseif and continuing inline.',
            );
        }
        if ($node->else !== null) {
            $violations[] = new Violation(
                file: $context->file,
                line: $node->else->getLine(),
                rule: $this->name(),
                message: 'else branch follows an if that always returns/throws/exits — flatten by removing the else and continuing inline.',
            );
        }

        return $violations;
    }

    /**
     * @param list<Stmt> $stmts
     */
    private function lastStatementTerminates(array $stmts): bool
    {
        if ($stmts === []) {
            return false;
        }

        $last = $stmts[count($stmts) - 1];

        if ($last instanceof Stmt\Return_) {
            return true;
        }
        if ($last instanceof Stmt\Continue_ || $last instanceof Stmt\Break_) {
            return true;
        }
        if ($last instanceof Stmt\Expression) {
            $expr = $last->expr;
            if ($expr instanceof Expr\Exit_ || $expr instanceof Expr\Throw_) {
                return true;
            }
        }

        return false;
    }

    public function fixPrompt(): string
    {
        return (string) file_get_contents(__DIR__.'/prompts/no-else-after-return.md');
    }
}
