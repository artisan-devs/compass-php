<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class NamedArgumentsRule implements Rule
{
    public const NAME = 'named-arguments';

    public function name(): string
    {
        return self::NAME;
    }

    public function shortDescription(): string
    {
        return 'Constructor invocations must use named arguments.';
    }

    public function fixPrompt(): string
    {
        return self::loadPrompt(self::NAME);
    }

    private static function loadPrompt(string $name): string
    {
        $path = __DIR__.'/prompts/'.$name.'.md';
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Fix prompt missing: %s', $path));
        }

        return $contents;
    }

    public function nodeTypes(): array
    {
        return [New_::class, StaticCall::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        if ($node instanceof New_) {
            if ($node->class instanceof Node\Stmt\Class_) {
                return;
            }
            yield from $this->checkArgs(
                $node->args,
                $node->getLine(),
                $context,
                $this->describeNew($node),
            );

            return;
        }

        if ($node instanceof StaticCall && $this->isParentOrSelfConstructorCall($node)) {
            yield from $this->checkArgs(
                $node->args,
                $node->getLine(),
                $context,
                $this->describeStaticCall($node),
            );
        }
    }

    /**
     * @param array<Arg|Node\VariadicPlaceholder> $args
     * @return iterable<Violation>
     */
    private function checkArgs(array $args, int $line, Context $context, string $target): iterable
    {
        foreach ($args as $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }
            if ($arg->name !== null) {
                continue;
            }
            if ($arg->unpack) {
                continue;
            }

            yield new Violation(
                rule: self::NAME,
                message: sprintf('Positional argument passed to %s; use named arguments.', $target),
                file: $context->file,
                line: $arg->getLine() > 0 ? $arg->getLine() : $line,
            );
        }
    }

    private function isParentOrSelfConstructorCall(StaticCall $node): bool
    {
        if (! $node->name instanceof Node\Identifier || $node->name->toString() !== '__construct') {
            return false;
        }
        if (! $node->class instanceof Node\Name) {
            return false;
        }
        $target = $node->class->toLowerString();

        return $target === 'parent' || $target === 'self' || $target === 'static';
    }

    private function describeNew(New_ $node): string
    {
        if ($node->class instanceof Node\Name) {
            return 'new '.$node->class->toString().'()';
        }
        if ($node->class instanceof Node\Stmt\Class_) {
            return 'new (anonymous class)';
        }

        return 'new (dynamic class)';
    }

    private function describeStaticCall(StaticCall $node): string
    {
        $class = $node->class instanceof Node\Name ? $node->class->toString() : '?';

        return $class.'::__construct()';
    }
}
