---
name: fix-compass-readonly-classes
description: Promote a class to `readonly class` when every property is already readonly. Use when Compass reports a `readonly-classes` violation. Requires PHP 8.2+.
rule: readonly-classes
node_types: [PhpParser\Node\Stmt\Class_]
risk: medium
auto_fixable: true
verification: composer compass -- --no-baseline && composer test
---

# Fix `readonly-classes` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` points at the `class` keyword.

## What this rule enforces

When every declared property AND every promoted constructor parameter is `readonly`, declare the class itself `readonly class`. The class-level modifier:

- Drops the noisy per-property `readonly` modifier.
- Forbids dynamic properties at runtime (so `$obj->newField = 1` throws instead of silently mutating).
- Communicates the immutability contract at a glance.

## How to apply the fix

1. Open `<file>` at line `<line>`.
2. Confirm by reading the class:
   - Every `private/protected/public ?Type $name;` declaration has `readonly`.
   - Every promoted constructor param has `readonly`.
3. Add `readonly` between the existing class-level modifiers and `class`. Conventional order: `final readonly class`.
4. Remove the per-property and per-promoted-param `readonly` modifiers — they're now redundant (and PHP will reject them on a readonly class? actually it accepts them, but they're noise).
5. If the class extends a parent, the parent must also be `readonly` (PHP 8.2 enforces this). Either propagate `readonly` upward or revert this change.

## Before / after

```php
// before
final class OrderId
{
    public function __construct(
        public readonly string $value,
        public readonly string $prefix,
    ) {
    }
}

// after
final readonly class OrderId
{
    public function __construct(
        public string $value,
        public string $prefix,
    ) {
    }
}
```

## Stop conditions

- The class extends a non-readonly parent. PHP 8.2 forbids this. Either make the parent readonly (recursive!) or skip the fix and add `// @compass-ignore-file readonly-classes` with a reason.
- The class is `abstract`. Readonly classes can be abstract since PHP 8.3 — verify the project's PHP version.
- The class implements `__set` / `__unset` / `__get` magic methods that depend on dynamic property access. Readonly class forbids dynamic properties; the magic methods will break. Either remove the magic methods or skip the fix.
- The project's `composer.json` `require.php` is `< 8.2` — disable the rule.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
composer test
```

Expect zero `readonly-classes` rows for `<file>` and a green test suite.
