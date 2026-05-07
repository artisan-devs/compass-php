---
name: fix-compass-final-classes
description: Mark a non-abstract class as final unless it is genuinely intended for extension. Use when Compass reports a `final-classes` violation.
rule: final-classes
node_types: [PhpParser\Node\Stmt\Class_]
risk: medium
auto_fixable: true
verification: composer compass -- --no-baseline && composer test
---

# Fix `final-classes` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` points at the `class` keyword.

## What this rule enforces

Every concrete class declaration must be either `final` (no further extension) or `abstract` (intended as a base). The default for new code is `final`.

Marking classes `final` lets the runtime, the type system, and refactoring tools assume there are no surprise overrides. It also prevents inheritance from being used as a casual code-sharing mechanism — extension should be a deliberate design choice, not a default.

## How to apply the fix

1. Open `<file>` at line `<line>`. Read the class body and surrounding context.
2. Decide between **final** (default) and **abstract** based on intent:
   - **Final**: this class is a concrete implementation. No subclasses are expected. Add `final` before `class`.
   - **Abstract**: the class is genuinely incomplete and its concrete subclasses fill in behavior. Add `abstract` before `class`.
3. Confirm by searching the codebase for subclasses:
   ```bash
   grep -rn "extends \(\\\\\)\?<ClassName>" --include="*.php"
   ```
   - **Zero matches** → safe to add `final`.
   - **Matches inside `tests/` only** (test doubles, mock subclasses) → still safe to add `final`; rewrite those tests to use composition / interface mocks.
   - **Matches in production code** → the class IS being extended. Either:
     - Convert to `abstract` if all subclasses depend on it being incomplete; or
     - Leave it concrete and add `// @compass-ignore-file final-classes` at the top with a comment explaining the inheritance contract.
4. Add the modifier inline. Preserve all other modifiers and attributes.

## Before / after

```php
// before
class OrderService
{
    public function place(Order $order): void
    {
        // ...
    }
}
```

```php
// after
final class OrderService
{
    public function place(Order $order): void
    {
        // ...
    }
}
```

## Stop conditions

- The class is part of a public-API package where downstream consumers extend it. Marking it `final` is a breaking change — verify with the package owner before touching it.
- Mocking frameworks (PHPUnit `getMockBuilder`, Mockery `mock(ClassName::class)`) extend the class to create test doubles. Modern PHPUnit/Mockery support mocking final classes via the `final` policy in `phpunit.xml` (`enableEnforceTimeLimit="true"` paired with `bnf:final` extension); confirm the project's `phpunit.xml` configuration before claiming "yes, finalize this." If the project doesn't run with mocking-of-finals enabled, prefer leaving the class non-final and adding `// @compass-ignore-next-line final-classes` above the `class` keyword.
- The class extends another class. `final` is still legal and recommended — extension goes one direction, not both.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
composer test
```

Expect zero `final-classes` rows for `<file>` and a green test suite. If a test that previously mocked the class fails after `final`, you have two paths: configure the mocking framework to allow finals, or rewrite the test to use the class's interface.
