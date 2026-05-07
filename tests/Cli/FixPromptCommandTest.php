<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Cli;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Cli\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

final class FixPromptCommandTest extends TestCase
{
    public function test_emits_rule_prompt_and_violations_for_filtered_file(): void
    {
        $project = self::makeProject();

        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $exit = $tester->run([
            'command' => 'fix-prompt',
            '--filter' => ['src/A.php'],
            '--no-baseline' => true,
        ]);

        self::assertSame(0, $exit, $tester->getDisplay());
        $display = $tester->getDisplay();

        self::assertStringContainsString('rule: named-arguments', $display, 'rule fix-prompt frontmatter must be present');
        self::assertStringContainsString('## Violations to fix', $display);
        self::assertStringContainsString('"file": "src/A.php"', $display);
        self::assertStringContainsString('"rule": "named-arguments"', $display);
        self::assertStringContainsString('"line":', $display);
        self::assertStringNotContainsString('"file": "src/B.php"', $display, 'filter must exclude unrelated files');
    }

    public function test_rule_option_restricts_output_to_a_single_rule_block(): void
    {
        $project = self::makeProjectWithMultipleRules();

        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $exit = $tester->run([
            'command' => 'fix-prompt',
            '--rule' => 'constructor-property-promotion',
            '--no-baseline' => true,
        ]);

        self::assertSame(0, $exit, $tester->getDisplay());
        $display = $tester->getDisplay();
        self::assertStringContainsString('rule: constructor-property-promotion', $display);
        self::assertStringNotContainsString('rule: named-arguments', $display);
    }

    public function test_first_emits_only_the_first_violation_per_rule(): void
    {
        $project = self::makeProject();

        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $tester->run([
            'command' => 'fix-prompt',
            '--filter' => ['src/A.php'],
            '--first' => true,
            '--no-baseline' => true,
        ]);

        $display = $tester->getDisplay();
        $jsonStart = strpos($display, '```json');
        $jsonEnd = strpos($display, '```', (int) $jsonStart + 7);
        self::assertIsInt($jsonStart);
        self::assertIsInt($jsonEnd);
        $jsonBlock = substr($display, (int) $jsonStart + 7, (int) $jsonEnd - (int) $jsonStart - 7);
        $rows = json_decode(trim($jsonBlock), true);
        self::assertIsArray($rows);
        self::assertCount(1, $rows, '--first must reduce the violation list to a single row');
    }

    public function test_no_matches_emits_friendly_message_and_zero_exit(): void
    {
        $project = self::makeProject();

        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $exit = $tester->run([
            'command' => 'fix-prompt',
            '--filter' => ['src/DoesNotExist.php'],
            '--no-baseline' => true,
        ]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('No violations match', $tester->getDisplay());
    }

    private static function makeProject(): string
    {
        $dir = sys_get_temp_dir().'/compass-fixprompt-'.bin2hex(random_bytes(6));
        mkdir($dir.'/src', 0775, true);
        file_put_contents($dir.'/src/A.php', "<?php\n\$a = new \\DateTimeImmutable('now');\n\$b = new \\DateTimeImmutable('later');\n");
        file_put_contents($dir.'/src/B.php', "<?php\n\$c = new \\DateTimeImmutable('today');\n");
        file_put_contents($dir.'/compass.yaml', "paths:\n  - src\nrules:\n  - named-arguments\n");

        register_shutdown_function(static function () use ($dir): void {
            self::rrmdir($dir);
        });

        return $dir;
    }

    private static function makeProjectWithMultipleRules(): string
    {
        $dir = sys_get_temp_dir().'/compass-fixprompt-'.bin2hex(random_bytes(6));
        mkdir($dir.'/src', 0775, true);
        file_put_contents($dir.'/src/A.php', <<<'PHP'
<?php
class Foo {
    private string $x;
    public function __construct(string $x) {
        $this->x = $x;
    }
}
$a = new \DateTimeImmutable('now');
PHP);
        file_put_contents($dir.'/compass.yaml', "paths:\n  - src\nrules:\n  - named-arguments\n  - constructor-property-promotion\n");

        register_shutdown_function(static function () use ($dir): void {
            self::rrmdir($dir);
        });

        return $dir;
    }

    private static function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $dir.'/'.$entry;
            is_dir($full) ? self::rrmdir($full) : @unlink($full);
        }
        @rmdir($dir);
    }
}
