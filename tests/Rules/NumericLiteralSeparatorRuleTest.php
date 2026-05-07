<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Result;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Rules\NumericLiteralSeparatorRule;

final class NumericLiteralSeparatorRuleTest extends TestCase
{
    public function test_passing_fixtures_produce_no_violations(): void
    {
        self::assertSame([], $this->runFixtures(__DIR__.'/../Fixtures/numeric_literal_separator/passing')->violations);
    }

    public function test_failing_fixtures_emit_violations(): void
    {
        $result = $this->runFixtures(__DIR__.'/../Fixtures/numeric_literal_separator/failing');
        self::assertGreaterThanOrEqual(4, count($result->violations));
        foreach ($result->violations as $v) {
            self::assertSame('numeric-literal-separator', $v->rule);
        }

        $messages = implode("\n", array_map(static fn ($v) => $v->message, $result->violations));
        self::assertStringContainsString('10_485_760', $messages);
        self::assertStringContainsString('1_500_000', $messages);
        self::assertStringContainsString('9_999_999', $messages);
        self::assertStringContainsString('12_345.6789', $messages);
    }

    private function runFixtures(string $dir): Result
    {
        $config = new Configuration(
            projectRoot: $dir,
            paths: [$dir],
            exclude: [],
            rules: [new NumericLiteralSeparatorRule()],
            ignore: [],
            baseline: null,
        );

        return (new Runner($config, new IgnoreList([], [], $dir)))->run();
    }
}
