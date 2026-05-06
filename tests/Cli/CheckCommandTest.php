<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Cli;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Cli\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

final class CheckCommandTest extends TestCase
{
    public function test_clean_run_returns_zero(): void
    {
        $project = self::makeProject(<<<'PHP'
<?php
$a = new \DateTimeImmutable(datetime: 'now');
PHP);

        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);

        $exit = $tester->run(['command' => 'check']);

        self::assertSame(0, $exit, $tester->getDisplay());
        self::assertStringContainsString('0 violations', $tester->getDisplay());
    }

    public function test_violations_return_one(): void
    {
        $project = self::makeProject(<<<'PHP'
<?php
$a = new \DateTimeImmutable('now');
PHP);

        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);

        $exit = $tester->run(['command' => 'check']);

        self::assertSame(1, $exit);
        self::assertStringContainsString('named-arguments', $tester->getDisplay());
    }

    public function test_json_reporter_outputs_valid_json(): void
    {
        $project = self::makeProject(<<<'PHP'
<?php
$a = new \DateTimeImmutable('now');
PHP);

        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'check', '--reporter' => 'json', '--group-by' => 'none']);

        $decoded = json_decode($tester->getDisplay(), true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('violations', $decoded);
        self::assertSame('none', $decoded['groupedBy']);
        self::assertCount(1, $decoded['violations']);
    }

    public function test_text_reporter_group_by_rule_lists_each_rule_section(): void
    {
        $project = self::makeProject(<<<'PHP'
<?php
$a = new \DateTimeImmutable('now');
$b = new \DateTimeImmutable('later');
PHP);

        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'check', '--group-by' => 'rule']);

        $display = $tester->getDisplay();
        self::assertStringContainsString('[named-arguments] 2 violation(s)', $display);
    }

    public function test_text_reporter_group_by_file_sub_groups_by_rule(): void
    {
        $dir = sys_get_temp_dir().'/compass-cli-'.bin2hex(random_bytes(6));
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
$b = new \DateTimeImmutable('later');
PHP);
        file_put_contents($dir.'/compass.php', <<<'PHP'
<?php
return [
    'paths' => ['src'],
    'rules' => [
        \Sidetours\Compass\Rules\NamedArgumentsRule::class => [],
        \Sidetours\Compass\Rules\PromotedPropertiesRule::class => [],
    ],
];
PHP);
        register_shutdown_function(static function () use ($dir): void {
            self::rrmdir($dir);
        });

        $app = new Application($dir);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'check', '--group-by' => 'file']);

        $display = $tester->getDisplay();
        self::assertStringContainsString('src/A.php (3)', $display);
        self::assertStringContainsString('[named-arguments] (2)', $display);
        self::assertStringContainsString('[promoted-properties] (1)', $display);

        $filePos = strpos($display, 'src/A.php');
        $namedPos = strpos($display, '[named-arguments]');
        $promotedPos = strpos($display, '[promoted-properties]');
        self::assertIsInt($filePos);
        self::assertIsInt($namedPos);
        self::assertIsInt($promotedPos);
        self::assertLessThan($namedPos, $filePos, 'file header must come before its rule sub-headers');
        self::assertLessThan($promotedPos, $namedPos, 'rule sub-headers must be alphabetically sorted');
    }

    public function test_text_reporter_group_by_rule_sub_groups_by_file(): void
    {
        $dir = sys_get_temp_dir().'/compass-cli-'.bin2hex(random_bytes(6));
        mkdir($dir.'/src', 0775, true);
        file_put_contents($dir.'/src/A.php', <<<'PHP'
<?php
$a = new \DateTimeImmutable('now');
$b = new \DateTimeImmutable('later');
PHP);
        file_put_contents($dir.'/src/B.php', "<?php\n\$c = new \\DateTimeImmutable('today');\n");
        file_put_contents($dir.'/compass.php', <<<'PHP'
<?php
return [
    'paths' => ['src'],
    'rules' => [\Sidetours\Compass\Rules\NamedArgumentsRule::class => []],
];
PHP);
        register_shutdown_function(static function () use ($dir): void {
            self::rrmdir($dir);
        });

        $app = new Application($dir);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'check', '--group-by' => 'rule']);

        $display = $tester->getDisplay();
        self::assertStringContainsString('[named-arguments] 3 violation(s)', $display);
        self::assertStringContainsString('src/A.php (2)', $display);
        self::assertStringContainsString('src/B.php (1)', $display);

        $aPos = strpos($display, 'src/A.php');
        $bPos = strpos($display, 'src/B.php');
        self::assertIsInt($aPos);
        self::assertIsInt($bPos);
        $section = substr($display, (int) $aPos, (int) $bPos - (int) $aPos);
        self::assertSame(1, substr_count($section, 'src/A.php'), 'file path should appear once as sub-header, not repeated per violation');
    }

    public function test_json_reporter_group_by_file_returns_map_keyed_by_file(): void
    {
        $project = self::makeProject(<<<'PHP'
<?php
$a = new \DateTimeImmutable('now');
PHP);

        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'check', '--reporter' => 'json', '--group-by' => 'file']);

        $decoded = json_decode($tester->getDisplay(), true);
        self::assertSame('file', $decoded['groupedBy']);
        self::assertIsArray($decoded['violations']);
        $firstKey = array_key_first($decoded['violations']);
        self::assertIsString($firstKey);
        self::assertStringEndsWith('Sample.php', $firstKey);
    }

    public function test_html_reporter_writes_index_rule_and_file_pages(): void
    {
        $project = self::makeProject(<<<'PHP'
<?php
$a = new \DateTimeImmutable('now');
PHP);
        $outDir = sys_get_temp_dir().'/compass-html-'.bin2hex(random_bytes(6));
        register_shutdown_function(static function () use ($outDir): void {
            self::rrmdir($outDir);
        });

        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);

        $exit = $tester->run(['command' => 'check', '--reporter' => 'html', '--out' => $outDir]);

        self::assertSame(1, $exit, $tester->getDisplay());
        self::assertFileExists($outDir.'/index.html');
        self::assertFileExists($outDir.'/assets/styles.css');
        self::assertFileExists($outDir.'/assets/app.js');
        self::assertFileExists($outDir.'/rules/named-arguments.html');
        self::assertFileExists($outDir.'/files/src__Sample.php.html');

        $index = (string) file_get_contents($outDir.'/index.html');
        self::assertStringContainsString('Compass', $index);
        self::assertStringContainsString('named-arguments', $index);
        self::assertStringContainsString('src/Sample.php', $index);
        self::assertStringContainsString('rules/named-arguments.html', $index);
        self::assertStringContainsString('files/src__Sample.php.html', $index);

        $rulePage = (string) file_get_contents($outDir.'/rules/named-arguments.html');
        self::assertStringContainsString('named-arguments', $rulePage);
        self::assertStringContainsString('src/Sample.php', $rulePage);
        self::assertStringContainsString('href="../files/src__Sample.php.html#L', $rulePage);

        $filePage = (string) file_get_contents($outDir.'/files/src__Sample.php.html');
        self::assertStringContainsString('class="line line--violation"', $filePage);
        self::assertStringContainsString('DateTimeImmutable', $filePage);
        self::assertStringContainsString('href="../rules/named-arguments.html"', $filePage);
    }

    public function test_html_reporter_renders_clean_state_when_no_violations(): void
    {
        $project = self::makeProject(<<<'PHP'
<?php
$a = new \DateTimeImmutable(datetime: 'now');
PHP);
        $outDir = sys_get_temp_dir().'/compass-html-'.bin2hex(random_bytes(6));
        register_shutdown_function(static function () use ($outDir): void {
            self::rrmdir($outDir);
        });

        $app = new Application($project);
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);

        $exit = $tester->run(['command' => 'check', '--reporter' => 'html', '--out' => $outDir]);

        self::assertSame(0, $exit, $tester->getDisplay());
        self::assertFileExists($outDir.'/index.html');
        self::assertStringContainsString('No violations', (string) file_get_contents($outDir.'/index.html'));
    }

    public function test_html_reporter_without_out_errors(): void
    {
        $project = self::makeProject('<?php $a = 1;');

        $app = new Application($project);
        $app->setAutoExit(false);
        $app->setCatchExceptions(true);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'check', '--reporter' => 'html']);

        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringContainsString('--out', $tester->getDisplay());
    }

    public function test_invalid_group_by_returns_error(): void
    {
        $project = self::makeProject('<?php $a = 1;');

        $app = new Application($project);
        $app->setAutoExit(false);
        $app->setCatchExceptions(true);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'check', '--group-by' => 'bogus']);

        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Unknown group-by mode', $tester->getDisplay());
    }

    private static function makeProject(string $sourceFile): string
    {
        $dir = sys_get_temp_dir().'/compass-cli-'.bin2hex(random_bytes(6));
        mkdir($dir.'/src', 0775, true);
        file_put_contents($dir.'/src/Sample.php', $sourceFile);
        file_put_contents($dir.'/compass.php', <<<'PHP'
<?php
return [
    'paths' => ['src'],
    'rules' => [\Sidetours\Compass\Rules\NamedArgumentsRule::class => []],
];
PHP);

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
