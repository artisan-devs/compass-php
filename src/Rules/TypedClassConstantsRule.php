<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class TypedClassConstantsRule implements Rule
{
    public function name(): string
    {
        return 'typed-class-constants';
    }

    public function shortDescription(): string
    {
        return 'Class, interface, and enum constants must declare an explicit type (PHP 8.3+).';
    }

    public function nodeTypes(): array
    {
        return [Stmt\ClassConst::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        if (! $node instanceof Stmt\ClassConst) {
            return [];
        }
        if ($node->type !== null) {
            return [];
        }
        $names = array_map(static fn ($const): string => $const->name->name, $node->consts);

        return [new Violation(
            file: $context->file,
            line: $node->getLine(),
            rule: $this->name(),
            message: sprintf('Class constant %s lacks a type declaration; add a native type (PHP 8.3+).', implode(', ', $names)),
        )];
    }

    public function fixPrompt(): string
    {
        return (string) file_get_contents(__DIR__.'/prompts/typed-class-constants.md');
    }
}
