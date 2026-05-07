<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class NeverReturnTypeRule implements Rule
{
    public function name(): string
    {
        return 'never-return-type';
    }

    public function shortDescription(): string
    {
        return 'Functions/methods whose every code path throws or exits should declare a `: never` return type (PHP 8.1+).';
    }

    public function nodeTypes(): array
    {
        return [Stmt\Function_::class, Stmt\ClassMethod::class, Expr\Closure::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        if (! ($node instanceof Stmt\Function_ || $node instanceof Stmt\ClassMethod || $node instanceof Expr\Closure)) {
            return [];
        }
        if ($node->returnType === null) {
            return [];
        }
        if ($this->isAlreadyNever($node->returnType)) {
            return [];
        }
        if ($node instanceof Stmt\ClassMethod && $node->stmts === null) {
            return [];
        }
        $stmts = $node->stmts ?? [];
        if (! $this->bodyAlwaysAborts($stmts)) {
            return [];
        }

        return [new Violation(
            file: $context->file,
            line: $node->getLine(),
            rule: $this->name(),
            message: 'Body always throws or exits on every path; declare the return type as `: never`.',
        )];
    }

    private function isAlreadyNever(Node $type): bool
    {
        if ($type instanceof Identifier && strtolower($type->name) === 'never') {
            return true;
        }
        if ($type instanceof Node\Name && $type->toLowerString() === 'never') {
            return true;
        }

        return false;
    }

    /**
     * @param list<Stmt> $stmts
     */
    private function bodyAlwaysAborts(array $stmts): bool
    {
        if ($stmts === []) {
            return false;
        }

        $last = $stmts[count($stmts) - 1];

        if ($last instanceof Stmt\Expression) {
            $expr = $last->expr;
            if ($expr instanceof Expr\Throw_ || $expr instanceof Expr\Exit_) {
                return true;
            }
        }
        if ($last instanceof Stmt\If_) {
            if (! $this->bodyAlwaysAborts($last->stmts)) {
                return false;
            }
            if ($last->else === null) {
                return false;
            }
            foreach ($last->elseifs as $elseif) {
                if (! $this->bodyAlwaysAborts($elseif->stmts)) {
                    return false;
                }
            }

            return $this->bodyAlwaysAborts($last->else->stmts);
        }

        return false;
    }

    public function fixPrompt(): string
    {
        return (string) file_get_contents(__DIR__.'/prompts/never-return-type.md');
    }
}
