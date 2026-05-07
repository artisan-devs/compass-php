---
name: fix-compass-numeric-separator
description: Add underscore separators to large numeric literals. Use when Compass reports a `numeric-separator` violation. Requires PHP 7.4+.
rule: numeric-separator
node_types: [PhpParser\Node\Scalar\Int_, PhpParser\Node\Scalar\Float_]
risk: low
auto_fixable: true
verification: composer compass -- --no-baseline && composer test
---

# Fix `numeric-separator` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` points at the numeric literal.

## What this rule enforces

PHP 7.4+ allows underscores inside numeric literals as visual separators (`1_000_000` is the same as `1000000`). For numbers ≥ 10_000, add separators at every third digit from the right to make magnitude obvious at a glance.

## How to apply the fix

1. Open `<file>` at line `<line>`.
2. Replace the bare numeric literal with an underscored version. Insert `_` before every third digit going right-to-left **on the integer portion only**:
   - `1000` → `1000` (below threshold, leave alone)
   - `10000` → `10_000`
   - `1500000` → `1_500_000`
   - `0.000001` → `0.000001` (fractional part isn't separated; integer part is below threshold)
   - `12345.6789` → `12_345.6789`
3. If the literal is in a hex / binary / octal / scientific form, the rule should not have fired — report and skip.

## Before / after

```php
// before
public const int MAX_PAYLOAD_BYTES = 10485760;
private int $thresholdMs = 1500000;
$cents = 9999999;
$rate = 12345.6789;

// after
public const int MAX_PAYLOAD_BYTES = 10_485_760;
private int $thresholdMs = 1_500_000;
$cents = 9_999_999;
$rate = 12_345.6789;
```

## Stop conditions

- The number is a known **bit pattern** (timestamps, IDs, magic numbers from a spec). Underscores are still safe but the separator placement may not match the spec's grouping (e.g. timestamps are seven digits without natural triplets). Apply the standard right-to-left grouping anyway — it's syntactic only.
- The number is a **port number / HTTP status / numeric ID** at or above 10_000 (e.g. error code `12345`). The rule fires; apply the separator. Readers won't be confused.
- The project's `composer.json` `require.php` is `< 7.4`. Disable the rule.
- The literal is part of a **printf format string or regex pattern**. The Compass detector targets numeric AST nodes, not strings, so this shouldn't fire — report if it does.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
composer test
```

Expect zero `numeric-separator` rows for `<file>` and a green test suite. The transform is syntactic-only — tests should not regress.
