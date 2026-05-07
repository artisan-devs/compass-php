<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class TypedDeclarationsRule implements Rule
{
    /**
     * Magic methods that legitimately have no return type.
     *
     * @var list<string>
     */
    private const NO_RETURN_TYPE_METHODS = ['__construct', '__destruct'];

    public function name(): string
    {
        return 'typed-declarations';
    }

    public function shortDescription(): string
    {
        return 'Class properties, function/method parameters, and return types must be explicitly typed.';
    }

    public function nodeTypes(): array
    {
        return [
            Stmt\Property::class,
            Param::class,
            Stmt\Function_::class,
            Stmt\ClassMethod::class,
            Expr\Closure::class,
            Expr\ArrowFunction::class,
        ];
    }

    public function check(Node $node, Context $context): iterable
    {
        if ($node instanceof Stmt\Property) {
            if ($node->type !== null) {
                return [];
            }
            $names = array_map(static fn ($prop): string => '$'.$prop->name->name, $node->props);

            return [new Violation(
                file: $context->file,
                line: $node->getLine(),
                rule: $this->name(),
                message: sprintf('Property %s lacks an explicit type declaration; add a native type (PHPDoc @var is not a substitute).', implode(', ', $names)),
            )];
        }

        if ($node instanceof Param) {
            if ($node->type !== null) {
                return [];
            }
            $name = $this->paramName($node);

            return [new Violation(
                file: $context->file,
                line: $node->getLine(),
                rule: $this->name(),
                message: sprintf('Parameter %s lacks an explicit type declaration.', $name),
            )];
        }

        if ($node instanceof Stmt\Function_ || $node instanceof Stmt\ClassMethod || $node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            if ($node->returnType !== null) {
                return [];
            }
            if ($node instanceof Stmt\ClassMethod && in_array($node->name->name, self::NO_RETURN_TYPE_METHODS, true)) {
                return [];
            }
            $label = $this->callableLabel($node);

            return [new Violation(
                file: $context->file,
                line: $node->getLine(),
                rule: $this->name(),
                message: sprintf('%s lacks an explicit return type declaration; declare the return type or use ": void" for no return value.', $label),
            )];
        }

        return [];
    }

    private function paramName(Param $param): string
    {
        if ($param->var instanceof Expr\Variable && is_string($param->var->name)) {
            return '$'.$param->var->name;
        }

        return '<unknown>';
    }

    private function callableLabel(Node $node): string
    {
        if ($node instanceof Stmt\Function_) {
            return sprintf('Function %s()', $node->name->name);
        }
        if ($node instanceof Stmt\ClassMethod) {
            return sprintf('Method %s()', $node->name->name);
        }
        if ($node instanceof Expr\Closure) {
            return 'Closure';
        }
        if ($node instanceof Expr\ArrowFunction) {
            return 'Arrow function';
        }

        return 'Callable';
    }

    public function fixPrompt(): string
    {
        return (string) file_get_contents(__DIR__.'/prompts/typed-declarations.md');
    }
}
