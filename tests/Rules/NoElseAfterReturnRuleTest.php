<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Rules\NoElseAfterReturnRule;

final class NoElseAfterReturnRuleTest extends TestCase
{
    public function test_passing_fixtures_produce_no_violations(): void
    {
        $result = $this->runFixtures(__DIR__.'/../Fixtures/no_else_after_return/passing');
        self::assertSame([], $result->violations);
    }

    public function test_failing_fixtures_each_produce_violations(): void
    {
        $result = $this->runFixtures(__DIR__.'/../Fixtures/no_else_after_return/failing');
        self::assertGreaterThanOrEqual(2, count($result->violations));
        foreach ($result->violations as $v) {
            self::assertSame('no-else-after-return', $v->rule);
        }
    }

    private function runFixtures(string $dir): \Sidetours\Compass\Engine\Result
    {
        $config = new Configuration(
            projectRoot: $dir,
            paths: [$dir],
            exclude: [],
            rules: [new NoElseAfterReturnRule()],
            ignore: [],
            baseline: null,
        );

        return (new Runner($config, new IgnoreList([], [], $dir)))->run();
    }
}
