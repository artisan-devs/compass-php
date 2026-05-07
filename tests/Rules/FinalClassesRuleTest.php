<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Rules\FinalClassesRule;

final class FinalClassesRuleTest extends TestCase
{
    public function test_passing_fixtures_produce_no_violations(): void
    {
        $result = $this->runFixtures(__DIR__.'/../Fixtures/final_classes/passing');
        self::assertSame([], $result->violations);
    }

    public function test_failing_fixture_emits_violation(): void
    {
        $result = $this->runFixtures(__DIR__.'/../Fixtures/final_classes/failing');
        self::assertNotSame([], $result->violations);
        self::assertSame('final-classes', $result->violations[0]->rule);
        self::assertStringContainsString('PlainSample', $result->violations[0]->message);
    }

    private function runFixtures(string $dir): \Sidetours\Compass\Engine\Result
    {
        $config = new Configuration(
            projectRoot: $dir,
            paths: [$dir],
            exclude: [],
            rules: [new FinalClassesRule()],
            ignore: [],
            baseline: null,
        );

        return (new Runner($config, new IgnoreList([], [], $dir)))->run();
    }
}
