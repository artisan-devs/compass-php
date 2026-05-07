<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Property;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class ConstructorPropertyPromotionRule implements Rule
{
    public const NAME = 'constructor-property-promotion';

    public function name(): string
    {
        return self::NAME;
    }

    public function shortDescription(): string
    {
        return 'Constructor parameters that mirror a class property should use constructor property promotion.';
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
        return [Class_::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        if (! $node instanceof Class_) {
            return;
        }

        $constructor = $node->getMethod('__construct');
        if ($constructor === null || $constructor->stmts === null) {
            return;
        }

        $properties = $this->indexProperties($node);
        if ($properties === []) {
            return;
        }

        $assignments = $this->indexTrivialAssignments($constructor->stmts);

        foreach ($constructor->getParams() as $param) {
            if ($param->flags !== 0) {
                continue;
            }
            if (! $param->var instanceof Variable || ! is_string($param->var->name)) {
                continue;
            }

            $name = $param->var->name;
            if (! isset($properties[$name]) || ! isset($assignments[$name])) {
                continue;
            }

            yield new Violation(
                rule: self::NAME,
                message: sprintf('Parameter $%s mirrors property $%s; promote it via constructor property promotion.', $name, $name),
                file: $context->file,
                line: $param->getLine(),
            );
        }
    }

    /**
     * @return array<string, Property>
     */
    private function indexProperties(Class_ $class): array
    {
        $out = [];
        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $declared) {
                $out[$declared->name->toString()] = $property;
            }
        }

        return $out;
    }

    /**
     * Index direct `$this->X = $X;` statements by parameter name.
     *
     * @param Node\Stmt[] $stmts
     * @return array<string, Assign>
     */
    private function indexTrivialAssignments(array $stmts): array
    {
        $out = [];
        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Expression || ! $stmt->expr instanceof Assign) {
                continue;
            }
            $assign = $stmt->expr;

            if (! $assign->var instanceof PropertyFetch) {
                continue;
            }
            $target = $assign->var;
            if (! $target->var instanceof Variable || $target->var->name !== 'this') {
                continue;
            }
            if (! $target->name instanceof Node\Identifier) {
                continue;
            }

            if (! $assign->expr instanceof Variable || ! is_string($assign->expr->name)) {
                continue;
            }

            $propertyName = $target->name->toString();
            $variableName = $assign->expr->name;
            if ($propertyName !== $variableName) {
                continue;
            }

            $out[$variableName] = $assign;
        }

        return $out;
    }
}
