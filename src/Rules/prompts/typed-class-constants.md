---
name: fix-compass-typed-class-constants
description: Add explicit type declarations to class/interface/enum constants. Use when Compass reports a `typed-class-constants` violation. Requires PHP 8.3+.
rule: typed-class-constants
node_types: [PhpParser\Node\Stmt\ClassConst]
risk: low
auto_fixable: true
verification: composer compass -- --no-baseline && composer test
---

# Fix `typed-class-constants` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` points at the `const` declaration.

## What this rule enforces

PHP 8.3 added typed class constants. A typed constant is enforced at runtime when overridden by subclasses and exposes its type to static analysers.

## How to apply the fix

1. Open `<file>` at line `<line>`.
2. Read the constant's value and infer the narrowest reasonable type:
   - String literal → `string`
   - Integer literal → `int`
   - Float literal → `float`
   - `true`/`false` → `bool` (or use a literal type if the project's PHPStan level supports it)
   - Array literal → `array` (use a more specific type if PhpStan can infer it from context)
   - Enum case (`Status::Active`) → the enum's FQCN
3. Insert the type immediately after `const`.

## Before / after

```php
// before
final class Config
{
    public const VERSION = '1.0';
    private const MAX_RETRIES = 3;
    private const DEFAULTS = ['retries' => 3, 'timeout' => 30];
}

// after
final class Config
{
    public const string VERSION = '1.0';
    private const int MAX_RETRIES = 3;
    private const array DEFAULTS = ['retries' => 3, 'timeout' => 30];
}
```

## Stop conditions

- The project's `composer.json` `require.php` is `< 8.3`. The syntax is invalid on older runtimes — disable the rule via `compass.yaml` `ignore` or remove it from the `rules` list until the project upgrades.
- The constant is in a **trait** that's used by classes targeting older PHP. Same constraint.
- The constant value is a complex expression whose type would require a union or intersection. Pick the narrowest accurate union; if the union is unwieldy, keep it untyped and add `// @compass-ignore-next-line typed-class-constants` with a reason.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
composer test
```

Expect zero `typed-class-constants` rows for `<file>` and a green test suite.
