<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Result;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Rules\ReadonlyClassesRule;

final class ReadonlyClassesRuleTest extends TestCase
{
    public function test_passing_fixtures_produce_no_violations(): void
    {
        self::assertSame([], $this->runFixtures(__DIR__.'/../Fixtures/readonly_classes/passing')->violations);
    }

    public function test_failing_fixtures_emit_violations(): void
    {
        $result = $this->runFixtures(__DIR__.'/../Fixtures/readonly_classes/failing');
        self::assertGreaterThanOrEqual(1, count($result->violations));
        self::assertSame('readonly-classes', $result->violations[0]->rule);
    }

    private function runFixtures(string $dir): Result
    {
        $config = new Configuration(
            projectRoot: $dir,
            paths: [$dir],
            exclude: [],
            rules: [new ReadonlyClassesRule()],
            ignore: [],
            baseline: null,
        );

        return (new Runner($config, new IgnoreList([], [], $dir)))->run();
    }
}
