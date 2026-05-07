<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

final class StrictTypesDeclarationRule implements Rule, FileRule
{
    public function name(): string
    {
        return 'strict-types-declaration';
    }

    public function shortDescription(): string
    {
        return 'Every PHP file must begin with declare(strict_types=1); immediately after <?php.';
    }

    public function nodeTypes(): array
    {
        return [];
    }

    public function check(Node $node, Context $context): iterable
    {
        return [];
    }

    public function checkFile(Context $context): iterable
    {
        $source = $context->source;
        $trimmed = ltrim($source);
        if ($trimmed === '' || ! str_starts_with($trimmed, '<?php')) {
            return [];
        }

        if (preg_match('/^\s*<\?php\s+declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', $source) === 1) {
            return [];
        }

        return [new Violation(
            file: $context->file,
            line: 1,
            rule: $this->name(),
            message: 'Missing declare(strict_types=1); — every PHP file must declare strict types as the first statement after <?php.',
        )];
    }

    public function fixPrompt(): string
    {
        return (string) file_get_contents(__DIR__.'/prompts/strict-types-declaration.md');
    }
}
