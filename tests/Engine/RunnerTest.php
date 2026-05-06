<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Rules\NamedArgumentsRule;

final class RunnerTest extends TestCase
{
    public function test_scans_directory_and_reports_per_file_violations(): void
    {
        $dir = __DIR__.'/../Fixtures/named_arguments';

        $config = new Configuration(
            projectRoot: $dir,
            paths: [$dir],
            exclude: [],
            rules: [new NamedArgumentsRule()],
            ignore: [],
            baseline: null,
        );
        $runner = new Runner($config, new IgnoreList([], [], $dir));

        $result = $runner->run();

        self::assertGreaterThan(0, $result->filesScanned);
        self::assertGreaterThanOrEqual(3, count($result->violations), 'expected at least one violation per failing fixture');
        self::assertSame([], $result->errors);
    }

    public function test_baseline_suppresses_known_fingerprints(): void
    {
        $dir = __DIR__.'/../Fixtures/named_arguments/failing';

        $config = new Configuration(
            projectRoot: $dir,
            paths: [$dir],
            exclude: [],
            rules: [new NamedArgumentsRule()],
            ignore: [],
            baseline: null,
        );
        $unbaselined = (new Runner($config, new IgnoreList([], [], $dir)))->run();

        $fingerprints = array_map(static fn ($v) => $v->fingerprint(), $unbaselined->violations);
        $baselined = (new Runner($config, new IgnoreList([], $fingerprints, $dir)))->run();

        self::assertCount(0, $baselined->violations, 'baseline should suppress all known violations');
        self::assertSameSize($unbaselined->violations, $baselined->ignored);
    }

    public function test_exclude_skips_directory(): void
    {
        $dir = __DIR__.'/../Fixtures/named_arguments';

        $config = new Configuration(
            projectRoot: $dir,
            paths: [$dir],
            exclude: ['failing'],
            rules: [new NamedArgumentsRule()],
            ignore: [],
            baseline: null,
        );
        $result = (new Runner($config, new IgnoreList([], [], $dir)))->run();

        self::assertCount(0, $result->violations);
    }
}
