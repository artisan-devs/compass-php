<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class NamedMethodArgumentsRule implements Rule
{
    public const NAME = 'named-method-arguments';

    public function name(): string
    {
        return self::NAME;
    }

    public function shortDescription(): string
    {
        return 'Method and static call invocations must use named arguments.';
    }

    public function fixPrompt(): string
    {
        $path = __DIR__.'/prompts/'.self::NAME.'.md';
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Fix prompt missing: %s', $path));
        }

        return $contents;
    }

    public function nodeTypes(): array
    {
        return [MethodCall::class, NullsafeMethodCall::class, StaticCall::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        if ($node instanceof StaticCall && self::isConstructorCall($node)) {
            return;
        }

        if (! $node instanceof MethodCall && ! $node instanceof NullsafeMethodCall && ! $node instanceof StaticCall) {
            return;
        }

        yield from $this->checkArgs(
            $node->args,
            $node->getLine(),
            $context,
            $this->describeCall($node),
        );
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
            if ($arg->name !== null || $arg->unpack) {
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

    private static function isConstructorCall(StaticCall $node): bool
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

    private function describeCall(MethodCall|NullsafeMethodCall|StaticCall $node): string
    {
        $name = $node->name instanceof Node\Identifier ? $node->name->toString() : '{dynamic}';

        if ($node instanceof StaticCall) {
            $class = $node->class instanceof Node\Name ? $node->class->toString() : '{dynamic}';

            return sprintf('%s::%s()', $class, $name);
        }

        $arrow = $node instanceof NullsafeMethodCall ? '?->' : '->';

        return sprintf('%s%s()', $arrow, $name);
    }
}
