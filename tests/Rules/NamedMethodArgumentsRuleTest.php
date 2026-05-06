<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Rules\NamedMethodArgumentsRule;

final class NamedMethodArgumentsRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function fixtureProvider(): iterable
    {
        $base = __DIR__.'/../Fixtures/named_method_arguments';

        yield 'passing: all named' => [$base.'/passing/all_named.php', 0];
        yield 'passing: no args' => [$base.'/passing/no_args.php', 0];
        yield 'passing: variadic spread' => [$base.'/passing/variadic.php', 0];
        yield 'passing: parent::__construct skipped (handled by other rule)' => [$base.'/passing/parent_construct_only.php', 0];
        yield 'passing: func calls intentionally out of scope' => [$base.'/passing/func_call_skipped.php', 0];
        yield 'failing: method call positional' => [$base.'/failing/method_call.php', 1];
        yield 'failing: nullsafe method call positional' => [$base.'/failing/nullsafe.php', 1];
        yield 'failing: static call positional' => [$base.'/failing/static_call.php', 2];
        yield 'failing: mixed positional + named' => [$base.'/failing/mixed.php', 1];
    }

    #[DataProvider('fixtureProvider')]
    public function test_fixture_produces_expected_violations(string $fixture, int $expected): void
    {
        $config = new Configuration(
            projectRoot: dirname($fixture),
            paths: [$fixture],
            exclude: [],
            rules: [new NamedMethodArgumentsRule()],
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
}
