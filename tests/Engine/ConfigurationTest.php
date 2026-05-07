<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Rules\BuiltInRules;
use Sidetours\Compass\Rules\NamedArgumentsRule;
use Sidetours\Compass\Rules\ConstructorPropertyPromotionRule;
use Sidetours\Compass\Rules\Rule;
use Sidetours\Compass\Rules\StrictTypesDeclarationRule;
use Sidetours\Compass\Rules\TypeDeclarationsRule;
use Sidetours\Compass\Rules\ArraySpreadOperatorRule;
use Sidetours\Compass\Rules\StrContainsRule;

final class ConfigurationTest extends TestCase
{
    public function test_loads_yaml_with_short_rule_names(): void
    {
        $dir = self::scratchDir();
        file_put_contents($dir.'/compass.yaml', <<<'YAML'
paths:
  - src
rules:
  - named-arguments
  - constructor-property-promotion
YAML);

        $config = Configuration::load($dir.'/compass.yaml', $dir);

        self::assertCount(2, $config->rules);
        self::assertInstanceOf(NamedArgumentsRule::class, $config->rules[0]);
        self::assertInstanceOf(ConstructorPropertyPromotionRule::class, $config->rules[1]);
        self::assertSame([$dir.'/src'], $config->paths);
        self::assertNull($config->baseline);
    }

    public function test_loads_yaml_with_fqcn_rule(): void
    {
        $dir = self::scratchDir();
        file_put_contents($dir.'/compass.yaml', <<<'YAML'
paths:
  - src
rules:
  - "Sidetours\\Compass\\Rules\\NamedArgumentsRule"
YAML);

        $config = Configuration::load($dir.'/compass.yaml', $dir);

        self::assertCount(1, $config->rules);
        self::assertInstanceOf(NamedArgumentsRule::class, $config->rules[0]);
    }

    public function test_resolves_baseline_path_relative_to_project_root(): void
    {
        $dir = self::scratchDir();
        file_put_contents($dir.'/compass.yaml', <<<'YAML'
paths: [src]
rules: [named-arguments]
baseline: compass-baseline.php
YAML);

        $config = Configuration::load($dir.'/compass.yaml', $dir);

        self::assertSame($dir.'/compass-baseline.php', $config->baseline);
    }

    public function test_unknown_rule_short_name_raises_helpful_error(): void
    {
        $dir = self::scratchDir();
        file_put_contents($dir.'/compass.yaml', <<<'YAML'
paths: [src]
rules: [does-not-exist]
YAML);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown built-in rule "does-not-exist"');
        Configuration::load($dir.'/compass.yaml', $dir);
    }

    public function test_invalid_yaml_raises_runtime_exception(): void
    {
        $dir = self::scratchDir();
        file_put_contents($dir.'/compass.yaml', "paths: [src\nrules: [named-arguments]\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not valid YAML');
        Configuration::load($dir.'/compass.yaml', $dir);
    }

    public function test_missing_file_raises_runtime_exception(): void
    {
        $dir = self::scratchDir();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Compass config file not found');
        Configuration::load($dir.'/compass.yaml', $dir);
    }

    public function test_php_version_auto_includes_rules_up_to_target(): void
    {
        $dir = self::scratchDir();
        file_put_contents($dir.'/compass.yaml', <<<'YAML'
paths: [src]
phpVersion: "8.0"
YAML);

        $config = Configuration::load($dir.'/compass.yaml', $dir);

        $classes = array_map(static fn (Rule $r): string => $r::class, $config->rules);

        self::assertContains(StrictTypesDeclarationRule::class, $classes, 'PHP 7.0 rule should be included for target 8.0');
        self::assertContains(TypeDeclarationsRule::class, $classes, 'PHP 7.4 rule should be included for target 8.0');
        self::assertContains(ArraySpreadOperatorRule::class, $classes, 'PHP 7.4 rule should be included for target 8.0');
        self::assertContains(NamedArgumentsRule::class, $classes, 'PHP 8.0 rule should be included for target 8.0');
        self::assertContains(StrContainsRule::class, $classes, 'PHP 8.0 rule should be included for target 8.0');

        // 8.1+ rules should NOT be included.
        self::assertSame(
            BuiltInRules::applicableTo('8.0'),
            array_map(static fn (Rule $r): string => $r->name(), $config->rules),
        );
    }

    public function test_php_version_composes_with_explicit_rules_and_dedupes(): void
    {
        $dir = self::scratchDir();
        file_put_contents($dir.'/compass.yaml', <<<'YAML'
paths: [src]
phpVersion: "8.0"
rules:
  - final-classes
  - named-arguments
YAML);

        $config = Configuration::load($dir.'/compass.yaml', $dir);

        $names = array_map(static fn (Rule $r): string => $r->name(), $config->rules);

        // First two are the explicitly listed rules (preserving order).
        self::assertSame('final-classes', $names[0]);
        self::assertSame('named-arguments', $names[1]);

        // named-arguments appears exactly once even though phpVersion would also include it.
        self::assertSame(
            1,
            count(array_filter($names, static fn (string $n): bool => $n === 'named-arguments')),
        );

        // final-classes is only present because it was listed explicitly (phpVersion never auto-includes version-agnostic rules).
        self::assertContains('final-classes', $names);
    }

    public function test_php_version_invalid_format_raises_runtime_exception(): void
    {
        $dir = self::scratchDir();
        file_put_contents($dir.'/compass.yaml', <<<'YAML'
paths: [src]
phpVersion: "latest"
YAML);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not a recognised dotted version string');
        Configuration::load($dir.'/compass.yaml', $dir);
    }

    public function test_php_version_must_be_a_non_empty_string(): void
    {
        $dir = self::scratchDir();
        file_put_contents($dir.'/compass.yaml', <<<'YAML'
paths: [src]
phpVersion: ""
YAML);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('phpVersion');
        Configuration::load($dir.'/compass.yaml', $dir);
    }

    public function test_config_with_no_rules_and_no_php_version_raises_runtime_exception(): void
    {
        $dir = self::scratchDir();
        file_put_contents($dir.'/compass.yaml', <<<'YAML'
paths: [src]
YAML);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('at least one rule');
        Configuration::load($dir.'/compass.yaml', $dir);
    }

    public function test_explicit_duplicate_rules_are_deduped(): void
    {
        $dir = self::scratchDir();
        file_put_contents($dir.'/compass.yaml', <<<'YAML'
paths: [src]
rules:
  - named-arguments
  - "Sidetours\\Compass\\Rules\\NamedArgumentsRule"
YAML);

        $config = Configuration::load($dir.'/compass.yaml', $dir);

        self::assertCount(1, $config->rules);
        self::assertInstanceOf(NamedArgumentsRule::class, $config->rules[0]);
    }

    public function test_ignore_section_passes_through(): void
    {
        $dir = self::scratchDir();
        file_put_contents($dir.'/compass.yaml', <<<'YAML'
paths: [src]
rules: [named-arguments]
ignore:
  "src/Legacy/**":
    - "*"
  "tests/**":
    - named-arguments
YAML);

        $config = Configuration::load($dir.'/compass.yaml', $dir);

        self::assertSame(['*'], $config->ignore['src/Legacy/**']);
        self::assertSame(['named-arguments'], $config->ignore['tests/**']);
    }

    private static function scratchDir(): string
    {
        $dir = sys_get_temp_dir().'/compass-config-'.bin2hex(random_bytes(6));
        mkdir($dir, 0775, true);
        register_shutdown_function(static function () use ($dir): void {
            if (! is_dir($dir)) {
                return;
            }
            foreach (scandir($dir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                @unlink($dir.'/'.$entry);
            }
            @rmdir($dir);
        });

        return $dir;
    }
}
