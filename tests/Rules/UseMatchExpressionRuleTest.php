<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Result;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Rules\UseMatchExpressionRule;

final class UseMatchExpressionRuleTest extends TestCase
{
    public function test_passing_fixtures_produce_no_violations(): void
    {
        self::assertSame([], $this->runFixtures(__DIR__.'/../Fixtures/use_match_expression/passing')->violations);
    }

    public function test_clean_switch_emits_violation(): void
    {
        $result = $this->runFixtures(__DIR__.'/../Fixtures/use_match_expression/failing');
        self::assertCount(1, $result->violations);
        self::assertSame('use-match-expression', $result->violations[0]->rule);
    }

    private function runFixtures(string $dir): Result
    {
        $config = new Configuration(
            projectRoot: $dir,
            paths: [$dir],
            exclude: [],
            rules: [new UseMatchExpressionRule()],
            ignore: [],
            baseline: null,
        );

        return (new Runner($config, new IgnoreList([], [], $dir)))->run();
    }
}
