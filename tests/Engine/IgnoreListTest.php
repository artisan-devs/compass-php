<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Violation;

final class IgnoreListTest extends TestCase
{
    public function test_baseline_fingerprint_match_ignores_violation(): void
    {
        $violation = new Violation(rule: 'named-arguments', message: 'msg', file: '/proj/src/Foo.php', line: 10);
        $list = new IgnoreList(patterns: [], baseline: [$violation->fingerprint()], projectRoot: '/proj');

        self::assertTrue($list->isIgnored($violation));
    }

    public function test_pattern_with_wildcard_rule_ignores_any_rule(): void
    {
        $list = new IgnoreList(
            patterns: ['src/Legacy/**' => ['*']],
            baseline: [],
            projectRoot: '/proj',
        );

        $a = new Violation(rule: 'named-arguments', message: 'm', file: '/proj/src/Legacy/Old.php', line: 1);
        $b = new Violation(rule: 'other-rule', message: 'm', file: '/proj/src/Legacy/Old.php', line: 1);

        self::assertTrue($list->isIgnored($a));
        self::assertTrue($list->isIgnored($b));
    }

    public function test_pattern_with_specific_rule_only_ignores_that_rule(): void
    {
        $list = new IgnoreList(
            patterns: ['src/Legacy/**' => ['named-arguments']],
            baseline: [],
            projectRoot: '/proj',
        );

        $matching = new Violation(rule: 'named-arguments', message: 'm', file: '/proj/src/Legacy/Old.php', line: 1);
        $other = new Violation(rule: 'other-rule', message: 'm', file: '/proj/src/Legacy/Old.php', line: 1);

        self::assertTrue($list->isIgnored($matching));
        self::assertFalse($list->isIgnored($other));
    }

    public function test_unmatched_path_is_not_ignored(): void
    {
        $list = new IgnoreList(
            patterns: ['src/Legacy/**' => ['*']],
            baseline: [],
            projectRoot: '/proj',
        );

        $violation = new Violation(rule: 'named-arguments', message: 'm', file: '/proj/src/Domain/Entity.php', line: 1);

        self::assertFalse($list->isIgnored($violation));
    }
}
