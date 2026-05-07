<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

/**
 * Enforces named arguments at every callsite where they're permitted:
 * constructor invocations (`new Foo(...)`, `parent::__construct(...)`),
 * method calls (`$x->method(...)`, `$x?->method(...)`), and static calls
 * (`Foo::method(...)`). Plain function calls are intentionally out of scope.
 *
 * This rule replaces the earlier split between `named-arguments` (constructors)
 * and `named-method-arguments` (everything else) — both enforced "no positional
 * args at callsites" and produced redundant violations on common patterns like
 * `$this->method(new X(...))`. Merging them into one rule keeps a single name
 * to remember and a single fix prompt covering all four call shapes.
 */
final class NamedArgumentsRule implements Rule
{
    public const NAME = 'named-arguments';

    public function name(): string
    {
        return self::NAME;
    }

    public function shortDescription(): string
    {
        return 'Constructor, method, and static call invocations must use named arguments.';
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
        return [New_::class, MethodCall::class, NullsafeMethodCall::class, StaticCall::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        if ($node instanceof New_) {
            // `new class { ... }` anonymous-class literals — out of scope.
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

        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall) {
            yield from $this->checkArgs(
                $node->args,
                $node->getLine(),
                $context,
                $this->describeCall($node),
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

    private function describeNew(New_ $node): string
    {
        if ($node->class instanceof Node\Name) {
            return 'new '.$node->class->toString().'()';
        }

        return 'new (dynamic class)';
    }

    private function describeCall(MethodCall|NullsafeMethodCall|StaticCall $node): string
    {
        $name = $node->name instanceof Node\Identifier ? $node->name->toString() : '{dynamic}';

        if ($node instanceof StaticCall) {
            $class = $node->class instanceof Node\Name ? $node->class->toString() : '{dynamic}';
            // Show parent::__construct / self::__construct / Foo::method consistently
            return sprintf('%s::%s()', $class, $name);
        }

        $arrow = $node instanceof NullsafeMethodCall ? '?->' : '->';

        return sprintf('%s%s()', $arrow, $name);
    }
}
