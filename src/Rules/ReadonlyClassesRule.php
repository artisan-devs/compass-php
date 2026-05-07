<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class ReadonlyClassesRule implements Rule
{
    public function name(): string
    {
        return 'readonly-classes';
    }

    public function shortDescription(): string
    {
        return 'A class whose every property is readonly should be declared `readonly class` (PHP 8.2+).';
    }

    public function nodeTypes(): array
    {
        return [Stmt\Class_::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        if (! $node instanceof Stmt\Class_) {
            return [];
        }
        if ($node->name === null) {
            return [];
        }
        if ($node->isReadonly()) {
            return [];
        }
        if ($node->isAbstract()) {
            return [];
        }

        $hasProperty = false;
        foreach ($node->getProperties() as $property) {
            $hasProperty = true;
            if (! $property->isReadonly()) {
                return [];
            }
        }

        $constructor = $node->getMethod('__construct');
        if ($constructor !== null) {
            foreach ($constructor->params as $param) {
                if ($param->flags === 0) {
                    continue;
                }
                $hasProperty = true;
                if (($param->flags & Modifiers::READONLY) !== Modifiers::READONLY) {
                    return [];
                }
            }
        }

        if (! $hasProperty) {
            return [];
        }

        return [new Violation(
            file: $context->file,
            line: $node->getLine(),
            rule: $this->name(),
            message: sprintf('Class %s has only readonly properties; declare it as `readonly class` (PHP 8.2+) and drop the per-property modifier.', $node->name->name),
        )];
    }

    public function fixPrompt(): string
    {
        return (string) file_get_contents(__DIR__.'/prompts/readonly-classes.md');
    }
}
