<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Rules\TypeDeclarationsRule;

final class TypeDeclarationsRuleTest extends TestCase
{
    public function test_passing_fixtures_produce_no_violations(): void
    {
        $result = $this->runFixtures(__DIR__.'/../Fixtures/type_declarations/passing');
        self::assertSame([], $result->violations);
    }

    public function test_each_failing_fixture_emits_at_least_one_violation(): void
    {
        $result = $this->runFixtures(__DIR__.'/../Fixtures/type_declarations/failing');
        self::assertGreaterThanOrEqual(3, count($result->violations));

        $messages = implode("\n", array_map(static fn ($v) => $v->message, $result->violations));
        self::assertStringContainsString('Property', $messages);
        self::assertStringContainsString('Parameter', $messages);
        self::assertStringContainsString('return type', $messages);

        foreach ($result->violations as $v) {
            self::assertSame('type-declarations', $v->rule);
        }
    }

    private function runFixtures(string $dir): \Sidetours\Compass\Engine\Result
    {
        $config = new Configuration(
            projectRoot: $dir,
            paths: [$dir],
            exclude: [],
            rules: [new TypeDeclarationsRule()],
            ignore: [],
            baseline: null,
        );

        return (new Runner($config, new IgnoreList([], [], $dir)))->run();
    }
}
