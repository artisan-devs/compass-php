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

    public function test_every_registered_rule_appears_in_exactly_one_category(): void
    {
        $registered = array_keys(BuiltInRules::MAP);
        sort($registered);

        $categorised = [];
        foreach (BuiltInRules::CATEGORIES as $category => $names) {
            self::assertIsString($category);
            self::assertNotEmpty($names, sprintf('Category "%s" has no rules.', $category));
            foreach ($names as $name) {
                self::assertArrayHasKey(
                    $name,
                    BuiltInRules::MAP,
                    sprintf('CATEGORIES references "%s" but it is not in MAP.', $name),
                );
                $categorised[] = $name;
            }
        }

        $duplicates = array_keys(array_filter(array_count_values($categorised), static fn (int $n): bool => $n > 1));
        self::assertSame([], $duplicates, 'Each rule must appear in exactly one category. Duplicates: '.implode(', ', $duplicates));

        sort($categorised);
        self::assertSame($registered, $categorised, 'Every rule in MAP must be assigned to a CATEGORIES bucket.');
    }

    public function test_categories_match_schema_anyof_enums(): void
    {
        $schemaByTitle = $this->loadSchemaEnumBranches();

        $expectedTitles = [
            'type-safety' => 'Type Safety',
            'modern-php' => 'Modern PHP',
            'code-hygiene' => 'Code Hygiene',
            'architecture' => 'Architecture',
        ];

        foreach (BuiltInRules::CATEGORIES as $category => $rules) {
            $title = $expectedTitles[$category] ?? null;
            self::assertNotNull($title, sprintf('No display title mapped for category "%s".', $category));
            self::assertArrayHasKey($title, $schemaByTitle, sprintf('compass.schema.json is missing an anyOf branch titled "%s".', $title));

            $expected = $rules;
            $actual = $schemaByTitle[$title];
            sort($expected);
            sort($actual);
            self::assertSame(
                $expected,
                $actual,
                sprintf('Schema enum for category "%s" diverges from BuiltInRules::CATEGORIES.', $title),
            );
        }
    }

    public function test_php_versions_match_schema_anyof_enums(): void
    {
        $schemaByTitle = $this->loadSchemaEnumBranches();

        foreach (BuiltInRules::PHP_VERSIONS as $version => $rules) {
            $title = 'PHP '.$version;
            self::assertArrayHasKey($title, $schemaByTitle, sprintf('compass.schema.json is missing an anyOf branch titled "%s".', $title));

            $expected = $rules;
            $actual = $schemaByTitle[$title];
            sort($expected);
            sort($actual);
            self::assertSame(
                $expected,
                $actual,
                sprintf('Schema enum for PHP version "%s" diverges from BuiltInRules::PHP_VERSIONS.', $version),
            );
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function loadSchemaEnumBranches(): array
    {
        $schemaPath = __DIR__.'/../../compass.schema.json';
        $schema = json_decode((string) file_get_contents($schemaPath), true);
        self::assertIsArray($schema);

        /** @var list<array<string, mixed>> $anyOf */
        $anyOf = $schema['properties']['rules']['items']['anyOf'] ?? [];

        $byTitle = [];
        foreach ($anyOf as $branch) {
            if (! isset($branch['enum']) || ! is_array($branch['enum'])) {
                continue;
            }
            $title = (string) ($branch['title'] ?? '');
            /** @var list<string> $enum */
            $enum = array_values($branch['enum']);
            $byTitle[$title] = $enum;
        }

        return $byTitle;
    }

    public function test_php_versions_only_reference_rules_in_the_registry(): void
    {
        foreach (BuiltInRules::PHP_VERSIONS as $version => $rules) {
            self::assertIsString($version);
            self::assertMatchesRegularExpression(
                '/^\d+(\.\d+){1,2}$/',
                $version,
                sprintf('PHP_VERSIONS key "%s" is not a recognised dotted version string.', $version),
            );
            self::assertNotEmpty($rules, sprintf('PHP_VERSIONS bucket "%s" is empty.', $version));
            foreach ($rules as $rule) {
                self::assertArrayHasKey(
                    $rule,
                    BuiltInRules::MAP,
                    sprintf('PHP_VERSIONS["%s"] references "%s" which is not in MAP.', $version, $rule),
                );
            }
        }
    }

    public function test_each_rule_is_introduced_in_at_most_one_php_version(): void
    {
        $seen = [];
        foreach (BuiltInRules::PHP_VERSIONS as $version => $rules) {
            foreach ($rules as $rule) {
                self::assertArrayNotHasKey(
                    $rule,
                    $seen,
                    sprintf('Rule "%s" is listed under both "%s" and "%s" in PHP_VERSIONS.', $rule, $seen[$rule] ?? '?', $version),
                );
                $seen[$rule] = $version;
            }
        }
    }

    public function test_applicable_to_aggregates_rules_up_to_the_given_php_version(): void
    {
        $applicable = BuiltInRules::applicableTo('8.0');
        self::assertContains('strict-types-declaration', $applicable);
        self::assertContains('array-spread-operator', $applicable);
        self::assertContains('named-arguments', $applicable);
        self::assertNotContains('first-class-callable-syntax', $applicable, 'first-class-callable-syntax was introduced in 8.1');
        self::assertNotContains('readonly-classes', $applicable, 'readonly-classes was introduced in 8.2');
        self::assertNotContains('typed-class-constants', $applicable, 'typed-class-constants was introduced in 8.3');
    }

    public function test_applicable_to_includes_every_versioned_rule_for_the_latest_target(): void
    {
        $expected = [];
        foreach (BuiltInRules::PHP_VERSIONS as $rules) {
            foreach ($rules as $rule) {
                $expected[] = $rule;
            }
        }
        sort($expected);

        $actual = BuiltInRules::applicableTo('8.3');
        sort($actual);

        self::assertSame($expected, $actual);
    }

    public function test_applicable_to_handles_patch_versions(): void
    {
        self::assertContains('typed-class-constants', BuiltInRules::applicableTo('8.3.0'));
        self::assertContains('typed-class-constants', BuiltInRules::applicableTo('8.3.7'));
    }

    public function test_applicable_to_rejects_invalid_version_strings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not a recognised dotted version string');
        BuiltInRules::applicableTo('latest');
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
        $fqcn = \Sidetours\Compass\Rules\ConstructorPropertyPromotionRule::class;
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
