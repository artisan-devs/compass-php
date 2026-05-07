<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class FirstClassCallableRule implements Rule
{
    /**
     * Map of well-known callable-consuming function name => index of the callable argument.
     *
     * @var array<string, int>
     */
    private const CALLABLE_ARG_POSITION = [
        'array_map' => 0,
        'array_filter' => 1,
        'array_walk' => 1,
        'array_walk_recursive' => 1,
        'array_reduce' => 1,
        'usort' => 1,
        'uasort' => 1,
        'uksort' => 1,
        'call_user_func' => 0,
        'call_user_func_array' => 0,
    ];

    public function name(): string
    {
        return 'first-class-callable';
    }

    public function shortDescription(): string
    {
        return 'Use first-class callable syntax (Foo::bar(...) or $obj->bar(...)) instead of array-callable or string-callable forms (PHP 8.1+).';
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
        $fn = $node->name->toLowerString();
        if (! isset(self::CALLABLE_ARG_POSITION[$fn])) {
            return [];
        }
        $position = self::CALLABLE_ARG_POSITION[$fn];
        $args = $node->getArgs();
        if (! isset($args[$position])) {
            return [];
        }
        $callable = $args[$position]->value;

        if ($callable instanceof Expr\Array_ && count($callable->items) === 2) {
            $second = $callable->items[1];
            if ($second->value instanceof Scalar\String_) {
                return [new Violation(
                    file: $context->file,
                    line: $callable->getLine(),
                    rule: $this->name(),
                    message: sprintf('Replace array-callable in %s() with first-class callable syntax (e.g. Foo::%s(...) or $obj->%s(...)).', $fn, $second->value->value, $second->value->value),
                )];
            }
        }

        if ($callable instanceof Scalar\String_ && $this->looksLikeFunctionName($callable->value)) {
            return [new Violation(
                file: $context->file,
                line: $callable->getLine(),
                rule: $this->name(),
                message: sprintf('Replace string-callable "%s" in %s() with first-class callable: %s(...).', $callable->value, $fn, $callable->value),
            )];
        }

        return [];
    }

    private function looksLikeFunctionName(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $value) === 1;
    }

    public function fixPrompt(): string
    {
        return (string) file_get_contents(__DIR__.'/prompts/first-class-callable.md');
    }
}
