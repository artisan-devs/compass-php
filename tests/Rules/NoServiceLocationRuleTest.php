<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Rules\NoServiceLocationRule;

final class NoServiceLocationRuleTest extends TestCase
{
    public function test_passing_fixtures_produce_no_violations(): void
    {
        $result = $this->runFixtures(__DIR__.'/../Fixtures/no_service_location/passing');
        self::assertSame([], $result->violations, 'Infrastructure paths and DI patterns must not trigger the rule.');
    }

    public function test_failing_fixtures_emit_violations_in_domain_and_application_paths(): void
    {
        $result = $this->runFixtures(__DIR__.'/../Fixtures/no_service_location/failing');
        self::assertGreaterThanOrEqual(3, count($result->violations));

        $messages = implode("\n", array_map(static fn ($v) => $v->message, $result->violations));
        self::assertStringContainsString('Domain', $messages);
        self::assertStringContainsString('Application', $messages);

        foreach ($result->violations as $v) {
            self::assertSame('no-service-location', $v->rule);
        }
    }

    private function runFixtures(string $dir): \Sidetours\Compass\Engine\Result
    {
        $config = new Configuration(
            projectRoot: $dir,
            paths: [$dir],
            exclude: [],
            rules: [new NoServiceLocationRule()],
            ignore: [],
            baseline: null,
        );

        return (new Runner($config, new IgnoreList([], [], $dir)))->run();
    }
}
