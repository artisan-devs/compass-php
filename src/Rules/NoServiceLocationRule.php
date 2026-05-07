<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class NoServiceLocationRule implements Rule
{
    /**
     * Functions that perform service location and must not appear in Domain/Application code.
     *
     * @var list<string>
     */
    private const FORBIDDEN_FUNCTIONS = ['app', 'resolve'];

    /**
     * Static-call shapes that perform service location: ClassName::method.
     *
     * @var array<string, list<string>>
     */
    private const FORBIDDEN_STATIC_CALLS = [
        'App' => ['make'],
        'Container' => ['make'],
    ];

    public function name(): string
    {
        return 'no-service-location';
    }

    public function shortDescription(): string
    {
        return 'No service location (app(), resolve(), App::make()) inside Domain/Application layers; inject dependencies through the constructor instead.';
    }

    public function nodeTypes(): array
    {
        return [Expr\FuncCall::class, Expr\StaticCall::class];
    }

    public function check(Node $node, Context $context): iterable
    {
        $layer = $this->layer($context->file);
        if ($layer === null) {
            return [];
        }

        if ($node instanceof Expr\FuncCall) {
            if (! $node->name instanceof Node\Name) {
                return [];
            }
            $name = $node->name->toLowerString();
            if (! in_array($name, self::FORBIDDEN_FUNCTIONS, true)) {
                return [];
            }

            return [new Violation(
                file: $context->file,
                line: $node->getLine(),
                rule: $this->name(),
                message: sprintf('Service location via %s() in the %s layer; inject the dependency through the constructor instead.', $name, $layer),
            )];
        }

        if ($node instanceof Expr\StaticCall) {
            if (! $node->class instanceof Node\Name || ! $node->name instanceof Node\Identifier) {
                return [];
            }
            $class = ltrim($node->class->toString(), '\\');
            $method = $node->name->name;
            if (! isset(self::FORBIDDEN_STATIC_CALLS[$class]) || ! in_array($method, self::FORBIDDEN_STATIC_CALLS[$class], true)) {
                return [];
            }

            return [new Violation(
                file: $context->file,
                line: $node->getLine(),
                rule: $this->name(),
                message: sprintf('Service location via %s::%s() in the %s layer; inject the dependency through the constructor instead.', $class, $method, $layer),
            )];
        }

        return [];
    }

    private function layer(string $file): ?string
    {
        if (str_contains($file, '/Domain/') || str_contains($file, '\\Domain\\')) {
            return 'Domain';
        }
        if (str_contains($file, '/Application/') || str_contains($file, '\\Application\\')) {
            return 'Application';
        }

        return null;
    }

    public function fixPrompt(): string
    {
        return (string) file_get_contents(__DIR__.'/prompts/no-service-location.md');
    }
}
