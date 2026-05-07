---
name: fix-compass-never-return-type
description: Declare `: never` on functions/methods whose every code path throws or exits. Use when Compass reports a `never-return-type` violation. Requires PHP 8.1+.
rule: never-return-type
node_types: [PhpParser\Node\Stmt\Function_, PhpParser\Node\Stmt\ClassMethod, PhpParser\Node\Expr\Closure]
risk: low
auto_fixable: true
verification: composer compass -- --no-baseline && composer test
---

# Fix `never-return-type` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` points at the function/method header.

## What this rule enforces

A function whose every path ends in `throw` or `exit` does not return. Declaring its return type as `: never` tells PHP, your IDE, and PHPStan that nothing follows the call. This:

- Lets the type system narrow flow correctly: code after a `: never` call is unreachable.
- Communicates intent ("this is a guard / panic helper").
- Catches refactor regressions: if someone later adds a `return` path, PHP errors on the type mismatch.

## How to apply the fix

1. Open `<file>` at line `<line>`. Read the body and confirm every path aborts.
2. Replace the existing return type (likely `: void` or `: SomeType`) with `: never`.
3. Remove any unreachable trailing statements (e.g. dead `return null;` after a `throw`).

## Before / after

```php
// before
private function fail(string $msg): void
{
    throw new RuntimeException($msg);
}

private function panic(int $code): never
{
    if ($code === 0) {
        throw new InvalidArgumentException('zero');
    }
    exit($code);
}

// after
private function fail(string $msg): never
{
    throw new RuntimeException($msg);
}
// (panic was already correct)
```

## Stop conditions

- The function has a `try/catch` where the catch path returns normally. The function's overall behaviour isn't `: never` — leave it.
- The function uses recursion that may eventually return. PHPStan's static analysis is the source of truth — defer.
- The function passes the violation's "every path throws" check by accident (e.g. a `match` whose arms all throw). The rule doesn't always pierce expression-level abnormal completion — confirm with PHPStan that adding `: never` doesn't surface unreachable-code warnings.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
composer phpstan
composer test
```

Expect zero `never-return-type` rows for `<file>` and green PHPStan + tests. If PHPStan flags any *caller* code as unreachable after this change, that's a NEW finding worth investigating — most likely the caller had a fallback path the type system can now prove dead.
