<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Rules\ConstructorPropertyPromotionRule;

final class ConstructorPropertyPromotionRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function fixtureProvider(): iterable
    {
        $base = __DIR__.'/../Fixtures/constructor_property_promotion';

        yield 'passing: already promoted' => [$base.'/passing/already_promoted.php', 0];
        yield 'passing: assignment is transformed' => [$base.'/passing/transformed.php', 0];
        yield 'passing: property name does not match parameter' => [$base.'/passing/renamed.php', 0];
        yield 'passing: no class property' => [$base.'/passing/no_property.php', 0];
        yield 'passing: no constructor' => [$base.'/passing/no_constructor.php', 0];
        yield 'passing: abstract constructor (no body)' => [$base.'/passing/abstract_constructor.php', 0];
        yield 'failing: classic manual assignment' => [$base.'/failing/manual_assignment.php', 1];
        yield 'failing: two promotable params' => [$base.'/failing/two_params.php', 2];
        yield 'failing: one promotable, one transformed' => [$base.'/failing/mixed.php', 1];
    }

    #[DataProvider('fixtureProvider')]
    public function test_fixture_produces_expected_violations(string $fixture, int $expected): void
    {
        $config = new Configuration(
            projectRoot: dirname($fixture),
            paths: [$fixture],
            exclude: [],
            rules: [new ConstructorPropertyPromotionRule()],
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
