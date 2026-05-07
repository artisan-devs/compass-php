<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Rules\NamedArgumentsRule;

final class NamedArgumentsRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function fixtureProvider(): iterable
    {
        $base = __DIR__.'/../Fixtures/named_arguments';

        // Constructor-call cases.
        yield 'passing: all named' => [$base.'/passing/all_named.php', 0];
        yield 'passing: no args' => [$base.'/passing/no_args.php', 0];
        yield 'passing: variadic spread' => [$base.'/passing/variadic.php', 0];
        yield 'passing: anonymous class' => [$base.'/passing/anonymous_class.php', 0];
        yield 'failing: single positional' => [$base.'/failing/positional.php', 1];
        yield 'failing: parent constructor' => [$base.'/failing/parent_construct.php', 1];
        yield 'failing: mixed positional + named' => [$base.'/failing/mixed.php', 1];

        // Method/static-call cases (formerly the named-method-arguments rule, now merged in).
        yield 'passing: method calls all named' => [$base.'/passing/method_all_named.php', 0];
        yield 'passing: method calls no args' => [$base.'/passing/method_no_args.php', 0];
        yield 'passing: method variadic spread' => [$base.'/passing/method_variadic.php', 0];
        yield 'passing: func calls intentionally out of scope' => [$base.'/passing/func_call_skipped.php', 0];
        yield 'failing: method call positional' => [$base.'/failing/method_call.php', 1];
        yield 'failing: nullsafe method call positional' => [$base.'/failing/nullsafe.php', 1];
        yield 'failing: static call positional' => [$base.'/failing/static_call.php', 2];
        yield 'failing: method mixed positional + named' => [$base.'/failing/method_mixed.php', 1];
    }

    #[DataProvider('fixtureProvider')]
    public function test_fixture_produces_expected_violations(string $fixture, int $expected): void
    {
        $config = new Configuration(
            projectRoot: dirname($fixture),
            paths: [$fixture],
            exclude: [],
            rules: [new NamedArgumentsRule()],
            ignore: [],
            baseline: null,
        );
        $runner = new Runner($config, new IgnoreList([], [], $config->projectRoot));

        $result = $runner->run();

        self::assertSame([], $result->errors, 'parser errors: '.implode(', ', $result->errors));
        self::assertCount(
            $expected,
            $result->violations,
            sprintf(
                "Expected %d violations in %s, got %d:\n%s",
                $expected,
                basename($fixture),
                count($result->violations),
                implode("\n", array_map(static fn ($v) => $v->message.' @ '.$v->file.':'.$v->line, $result->violations)),
            ),
        );
    }

    public function test_inline_ignore_suppresses_violation(): void
    {
        $tmp = self::writeTemp(<<<'PHP'
<?php
$a = new \DateTimeImmutable('now'); // @compass-ignore named-arguments
$b = new \DateTimeImmutable('now');
PHP);

        $config = new Configuration(
            projectRoot: dirname($tmp),
            paths: [$tmp],
            exclude: [],
            rules: [new NamedArgumentsRule()],
            ignore: [],
            baseline: null,
        );
        $runner = new Runner($config, new IgnoreList([], [], $config->projectRoot));

        $result = $runner->run();

        self::assertCount(1, $result->violations, 'first call should be suppressed, second should fail');
        self::assertCount(1, $result->ignored);
        self::assertSame(3, $result->violations[0]->line);
    }

    public function test_ignore_file_suppresses_all_violations(): void
    {
        $tmp = self::writeTemp(<<<'PHP'
<?php
// @compass-ignore-file
$a = new \DateTimeImmutable('now');
$b = new \DateTimeImmutable('now');
PHP);

        $config = new Configuration(
            projectRoot: dirname($tmp),
            paths: [$tmp],
            exclude: [],
            rules: [new NamedArgumentsRule()],
            ignore: [],
            baseline: null,
        );
        $runner = new Runner($config, new IgnoreList([], [], $config->projectRoot));

        $result = $runner->run();

        self::assertCount(0, $result->violations);
        self::assertCount(2, $result->ignored);
    }

    private static function writeTemp(string $contents): string
    {
        $path = sys_get_temp_dir().'/compass-'.bin2hex(random_bytes(6)).'.php';
        file_put_contents($path, $contents);
        register_shutdown_function(static fn () => @unlink($path));

        return $path;
    }
}
