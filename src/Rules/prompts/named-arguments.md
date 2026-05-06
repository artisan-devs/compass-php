---
name: fix-compass-named-arguments
description: Convert positional arguments at constructor call sites (new ClassName(...) or parent::__construct(...)) into named arguments. Use this when Compass reports a `named-arguments` violation.
rule: named-arguments
node_types: [PhpParser\Node\Expr\New_, PhpParser\Node\Expr\StaticCall]
risk: low
auto_fixable: true
verification: composer compass -- --no-baseline
---

# Fix `named-arguments` violations

You are fixing one Compass violation. The caller passes you a row containing `file`, `line`, `rule`, and `message`. Apply the recipe below to that single location and stop.

## What this rule enforces

Every constructor invocation must use named arguments for every argument. Two call shapes are in scope:

1. `new ClassName(...)` — FQN, aliased import, or anonymous-class FQN is fine.
2. `parent::__construct(...)`, `self::__construct(...)`, `static::__construct(...)`.

Plain method or function calls are **out of scope** — a sibling rule (`named-method-arguments`) handles those.

## How to apply the fix

1. Open `<file>` at line `<line>` and read the surrounding context (~5 lines above and below).
2. Identify the constructor invocation. There may be multiple positional arguments on the same line and therefore multiple stacked violations — handle them all in one pass.
3. Resolve the target class:
   - Follow `use` aliases at the top of the file.
   - If `parent::__construct(...)`, read the immediate parent class's `__construct` signature.
   - If the class is in `vendor/`, you may open it read-only to inspect the signature.
4. For each positional argument, prefix it with the corresponding parameter name from the signature. Example: `'now'` becomes `datetime: 'now'`.
5. Keep the original argument order to minimise diff churn (named arguments allow reordering, but reordering is not required).
6. Do NOT modify:
   - `...$args` variadic spread arguments — leave them positional.
   - Arguments that already have a `name:` prefix.
   - `new class { ... }` anonymous-class literals — intentionally out of scope for this rule.
   - Calls inside `vendor/` files — fix only first-party code.

## Before / after

```php
// before
return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

// after
return new \DateTimeImmutable(
    datetime: 'now',
    timezone: new \DateTimeZone(timezone: 'UTC'),
);
```

## Stop conditions

- The constructor target cannot be statically resolved (dynamic class name, expression, missing source). Report the violation as needing human review and move on — do not guess parameter names.
- The call uses `func_get_args()`, `ReflectionMethod::getParameters()`, or another reflection trick that depends on argument order. Skip and flag for review.

## Verification

After editing, run:

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
```

Expect zero `named-arguments` lines for `<file>`. If new violations appear (parser error, broken syntax), revert and ask for help.
