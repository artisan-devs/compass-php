<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Rules\NamedArgumentsRule;
use Sidetours\Compass\Rules\ConstructorPropertyPromotionRule;
use Sidetours\Compass\Rules\Rule;

final class RuleFixPromptTest extends TestCase
{
    /**
     * @return iterable<string, array{Rule}>
     */
    public static function ruleProvider(): iterable
    {
        yield 'named-arguments' => [new NamedArgumentsRule()];
        yield 'constructor-property-promotion' => [new ConstructorPropertyPromotionRule()];
    }

    #[DataProvider('ruleProvider')]
    public function test_prompt_starts_with_yaml_frontmatter(Rule $rule): void
    {
        $prompt = $rule->fixPrompt();

        self::assertNotEmpty($prompt);
        self::assertStringStartsWith("---\n", $prompt, 'fix prompt must open with `---` YAML delimiter');
    }

    #[DataProvider('ruleProvider')]
    public function test_prompt_frontmatter_declares_required_keys(Rule $rule): void
    {
        $frontmatter = self::extractFrontmatter($rule->fixPrompt());

        self::assertArrayHasKey('name', $frontmatter);
        self::assertArrayHasKey('description', $frontmatter);
        self::assertArrayHasKey('rule', $frontmatter);
        self::assertSame($rule->name(), $frontmatter['rule'], 'frontmatter rule key must match rule->name()');
    }

    /**
     * Minimal YAML scalar parser sufficient for the keys we use.
     *
     * @return array<string, string>
     */
    private static function extractFrontmatter(string $prompt): array
    {
        if (! preg_match('/\A---\n(.*?)\n---\n/s', $prompt, $m)) {
            self::fail('No closing `---` marker found in prompt frontmatter');
        }

        $out = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (! preg_match('/^([a-zA-Z0-9_]+):\s*(.*)$/', $line, $kv)) {
                continue;
            }
            $out[$kv[1]] = trim($kv[2], " \"'");
        }

        return $out;
    }
}
