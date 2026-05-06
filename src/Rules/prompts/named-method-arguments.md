---
name: fix-compass-named-method-arguments
description: Convert positional arguments on method and static calls into named arguments. Use when Compass reports a `named-method-arguments` violation.
rule: named-method-arguments
node_types: [PhpParser\Node\Expr\MethodCall, PhpParser\Node\Expr\NullsafeMethodCall, PhpParser\Node\Expr\StaticCall]
risk: medium
auto_fixable: true
verification: composer compass -- --no-baseline
---

# Fix `named-method-arguments` violations

You are fixing one Compass violation. The caller passes you a row containing `file`, `line`, `rule`, and `message`. Apply the recipe below to that single location and stop.

## What this rule enforces

Every method, nullsafe-method, and static call must use named arguments. Three call shapes are in scope:

1. `$obj->method(...)`
2. `$obj?->method(...)`
3. `Foo::bar(...)` — except `parent::__construct`, `self::__construct`, `static::__construct`, which the `named-arguments` rule handles.

Plain function calls (`count($x)`, `strlen($s)`, etc.) are **out of scope**.

## How to apply the fix

1. Open `<file>` at line `<line>`. Read 5 lines of context around it.
2. Identify the call. Multiple positional args on the same line produce stacked violations — fix them all in one pass.
3. Resolve the receiver's type and locate the method signature:
   - For `$obj->method(...)`: walk back to the declaration of `$obj` and find its declared type. If it's a property, look at the property's type. If it's a parameter, look at its type hint.
   - For `Foo::bar(...)`: resolve `Foo` via `use` aliases or FQN.
   - If the method is inherited, follow the chain to find the actual signature.
   - You may open `vendor/` read-only to inspect signatures.
4. For each positional argument, add `<paramName>:` prefix matching the resolved signature.
5. Keep argument order stable.
6. Do NOT modify:
   - `...$args` spread arguments.
   - Arguments already prefixed with `name:`.
   - Mockery / `shouldReceive` / test-double DSL calls — these methods accept variadic strings positionally by design. Add the rule violation to the file's ignore list instead (`'tests/**' => ['named-method-arguments']` in `compass.php`) if it's a recurring pattern.
   - Calls inside `vendor/`.

## Before / after

```php
// before
return $clock->modify('+1 day');
$result = \DateTimeImmutable::createFromFormat('Y-m-d', '2026-01-01');

// after
return $clock->modify(modifier: '+1 day');
$result = \DateTimeImmutable::createFromFormat(format: 'Y-m-d', datetime: '2026-01-01');
```

## Stop conditions

- The receiver type cannot be inferred (dynamic call on `mixed`, untyped variable). Report and move on; do not guess.
- The method overrides one with renamed parameters — pick the parameter names from the actual implementation that will be called, not from a parent contract.
- The call passes a callable / closure where the position is meaningful (e.g. `usort($arr, $cmp)` — though that's a function, not a method, so it's out of scope anyway).

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
```

Expect no `named-method-arguments` lines for `<file>`. If syntax broke, revert.
