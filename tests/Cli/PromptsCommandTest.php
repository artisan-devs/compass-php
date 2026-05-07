<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Cli;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Cli\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

final class PromptsCommandTest extends TestCase
{
    public function test_prints_all_registered_rule_prompts(): void
    {
        $project = self::makeProject();
        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);

        $exit = $tester->run(['command' => 'prompts']);

        self::assertSame(0, $exit, $tester->getDisplay());
        $output = $tester->getDisplay();
        self::assertStringContainsString('rule: named-arguments', $output);
        self::assertStringContainsString('rule: constructor-property-promotion', $output);
    }

    public function test_filter_by_rule_emits_only_one_prompt(): void
    {
        $project = self::makeProject();
        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);

        $tester->run(['command' => 'prompts', '--rule' => 'constructor-property-promotion']);
        $output = $tester->getDisplay();

        self::assertStringContainsString('rule: constructor-property-promotion', $output);
        self::assertStringNotContainsString('rule: named-arguments', $output);
    }

    public function test_unknown_rule_returns_error(): void
    {
        $project = self::makeProject();
        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);

        $exit = $tester->run(['command' => 'prompts', '--rule' => 'does-not-exist']);

        self::assertSame(2, $exit);
        self::assertStringContainsString('No rule registered', $tester->getDisplay());
    }

    public function test_out_option_writes_one_file_per_rule(): void
    {
        $project = self::makeProject();
        $outDir = sys_get_temp_dir().'/compass-prompts-'.bin2hex(random_bytes(6));
        register_shutdown_function(static function () use ($outDir): void {
            if (! is_dir($outDir)) {
                return;
            }
            foreach (scandir($outDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                @unlink($outDir.'/'.$entry);
            }
            @rmdir($outDir);
        });

        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $exit = $tester->run(['command' => 'prompts', '--out' => $outDir]);

        self::assertSame(0, $exit, $tester->getDisplay());
        self::assertFileExists($outDir.'/named-arguments.md');
        self::assertFileExists($outDir.'/constructor-property-promotion.md');
    }

    private static function makeProject(): string
    {
        $dir = sys_get_temp_dir().'/compass-prompts-proj-'.bin2hex(random_bytes(6));
        mkdir($dir.'/src', 0775, true);
        file_put_contents($dir.'/compass.yaml', <<<'YAML'
paths:
  - src
rules:
  - named-arguments
  - constructor-property-promotion
YAML);

        register_shutdown_function(static function () use ($dir): void {
            if (! is_dir($dir)) {
                return;
            }
            foreach (scandir($dir.'/src') ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                @unlink($dir.'/src/'.$entry);
            }
            @rmdir($dir.'/src');
            @unlink($dir.'/compass.yaml');
            @rmdir($dir);
        });

        return $dir;
    }
}
