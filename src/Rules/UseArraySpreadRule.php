<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class UseArraySpreadRule implements Rule
{
    public function name(): string
    {
        return 'use-array-spread';
    }

    public function shortDescription(): string
    {
        return 'Replace array_merge(...) calls with array spread syntax (...$array) where it preserves semantics.';
    }

    public function nodeTypes(): array
    {
        return [Expr\FuncCall::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        if (! $node instanceof Expr\FuncCall) {
            return [];
        }
        if (! $node->name instanceof Node\Name) {
            return [];
        }
        if ($node->name->toLowerString() !== 'array_merge') {
            return [];
        }
        if (count($node->getArgs()) < 2) {
            return [];
        }

        return [new Violation(
            file: $context->file,
            line: $node->getLine(),
            rule: $this->name(),
            message: 'Replace array_merge() with array spread: [...$a, ...$b]. Watch out for string-keyed collisions where array_merge() and spread differ.',
        )];
    }

    public function fixPrompt(): string
    {
        return (string) file_get_contents(__DIR__.'/prompts/use-array-spread.md');
    }
}
