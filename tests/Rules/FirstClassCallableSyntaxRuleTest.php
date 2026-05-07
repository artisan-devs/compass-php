<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Result;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Rules\FirstClassCallableSyntaxRule;

final class FirstClassCallableSyntaxRuleTest extends TestCase
{
    public function test_passing_fixtures_produce_no_violations(): void
    {
        self::assertSame([], $this->runFixtures(__DIR__.'/../Fixtures/first_class_callable_syntax/passing')->violations);
    }

    public function test_failing_fixtures_emit_violations(): void
    {
        $result = $this->runFixtures(__DIR__.'/../Fixtures/first_class_callable_syntax/failing');
        self::assertGreaterThanOrEqual(3, count($result->violations));
        foreach ($result->violations as $v) {
            self::assertSame('first-class-callable-syntax', $v->rule);
        }
    }

    private function runFixtures(string $dir): Result
    {
        $config = new Configuration(
            projectRoot: $dir,
            paths: [$dir],
            exclude: [],
            rules: [new FirstClassCallableSyntaxRule()],
            ignore: [],
            baseline: null,
        );

        return (new Runner($config, new IgnoreList([], [], $dir)))->run();
    }
}
