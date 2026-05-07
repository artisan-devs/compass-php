<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class FinalClassesRule implements Rule
{
    public function name(): string
    {
        return 'final-classes';
    }

    public function shortDescription(): string
    {
        return 'Concrete (non-abstract) classes should be declared final unless designed for inheritance.';
    }

    public function nodeTypes(): array
    {
        return [Class_::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        if (! $node instanceof Class_) {
            return [];
        }
        if ($node->name === null) {
            return [];
        }
        if ($node->isFinal() || $node->isAbstract()) {
            return [];
        }

        return [new Violation(
            file: $context->file,
            line: $node->getLine(),
            rule: $this->name(),
            message: sprintf('Class %s should be declared final (or abstract if intended for extension).', $node->name->name),
        )];
    }

    public function fixPrompt(): string
    {
        return (string) file_get_contents(__DIR__.'/prompts/final-classes.md');
    }
}
