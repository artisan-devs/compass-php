<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Rules\StrictTypesDeclarationRule;

final class StrictTypesDeclarationRuleTest extends TestCase
{
    public function test_passing_fixtures_produce_no_violations(): void
    {
        $dir = __DIR__.'/../Fixtures/strict_types_declaration/passing';
        $result = $this->runFixtures($dir);

        self::assertSame([], $result->violations, 'expected zero violations for passing fixtures');
    }

    public function test_failing_fixtures_each_produce_at_least_one_violation(): void
    {
        $dir = __DIR__.'/../Fixtures/strict_types_declaration/failing';
        $result = $this->runFixtures($dir);

        self::assertGreaterThanOrEqual(2, count($result->violations), 'expected at least one violation per failing fixture');
        foreach ($result->violations as $v) {
            self::assertSame('strict-types-declaration', $v->rule);
            self::assertSame(1, $v->line);
        }
    }

    private function runFixtures(string $dir): \Sidetours\Compass\Engine\Result
    {
        $config = new Configuration(
            projectRoot: $dir,
            paths: [$dir],
            exclude: [],
            rules: [new StrictTypesDeclarationRule()],
            ignore: [],
            baseline: null,
        );

        return (new Runner($config, new IgnoreList([], [], $dir)))->run();
    }
}
