<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Rules\BuiltInRules;
use Sidetours\Compass\Rules\Rule;

final class BuiltInRulesTest extends TestCase
{
    public function test_every_built_in_rule_class_appears_in_the_registry(): void
    {
        $rulesDir = __DIR__.'/../../src/Rules';
        $found = [];
        foreach (glob($rulesDir.'/*Rule.php') ?: [] as $path) {
            $base = basename($path, '.php');
            if ($base === 'Rule') {
                continue;
            }
            $fqcn = 'Sidetours\\Compass\\Rules\\'.$base;
            if (! is_subclass_of($fqcn, Rule::class)) {
                continue;
            }
            $found[] = $fqcn;
        }

        $registered = array_values(BuiltInRules::MAP);
        sort($found);
        sort($registered);
        self::assertSame($found, $registered, 'Every built-in Rule subclass under src/Rules must be registered in BuiltInRules::MAP.');
    }

    public function test_each_registered_short_name_matches_the_classs_name_method(): void
    {
        foreach (BuiltInRules::MAP as $shortName => $fqcn) {
            $instance = new $fqcn();
            self::assertInstanceOf(Rule::class, $instance);
            self::assertSame(
                $shortName,
                $instance->name(),
                sprintf('BuiltInRules::MAP key "%s" must equal %s::name().', $shortName, $fqcn),
            );
        }
    }

    public function test_resolve_returns_built_in_fqcn_for_short_name(): void
    {
        self::assertSame(
            BuiltInRules::MAP['named-arguments'],
            BuiltInRules::resolve('named-arguments'),
        );
    }

    public function test_resolve_returns_fqcn_when_entry_contains_backslash(): void
    {
        $fqcn = \Sidetours\Compass\Rules\PromotedPropertiesRule::class;
        self::assertSame($fqcn, BuiltInRules::resolve($fqcn));
    }

    public function test_resolve_throws_for_unknown_short_name(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown built-in rule "no-such-rule"');
        BuiltInRules::resolve('no-such-rule');
    }

    public function test_resolve_throws_when_fqcn_does_not_implement_rule(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not a class implementing');
        BuiltInRules::resolve(\Sidetours\Compass\Engine\Configuration::class);
    }
}
