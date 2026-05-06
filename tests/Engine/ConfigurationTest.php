<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Rules\NamedArgumentsRule;
use Sidetours\Compass\Rules\PromotedPropertiesRule;

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
  - promoted-properties
YAML);

        $config = Configuration::load($dir.'/compass.yaml', $dir);

        self::assertCount(2, $config->rules);
        self::assertInstanceOf(NamedArgumentsRule::class, $config->rules[0]);
        self::assertInstanceOf(PromotedPropertiesRule::class, $config->rules[1]);
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
