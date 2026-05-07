---
name: fix-compass-array-spread-operator
description: Replace array_merge(...) calls with the array spread operator. Use when Compass reports a `array-spread-operator` violation.
rule: array-spread-operator
node_types: [PhpParser\Node\Expr\FuncCall]
risk: medium
auto_fixable: true
verification: composer compass -- --no-baseline && composer test
---

# Fix `array-spread-operator` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` points at the `array_merge(...)` call.

## What this rule enforces

Inline array merging via the spread operator (`...`) is more concise, plays well with type narrowing, and avoids the function-call indirection.

```php
// before
return array_merge($defaults, $extra);
return array_merge(['a' => 1], $rest);

// after
return [...$defaults, ...$extra];
return ['a' => 1, ...$rest];
```

## How to apply the fix

1. Open `<file>` at line `<line>`. Read the `array_merge` call and the surrounding context.
2. Verify each argument is either a literal array or a variable holding an array. (If any argument is a function call whose return type isn't statically known to be an array, leave it.)
3. Rewrite the call:
   - Drop `array_merge(`.
   - Wrap in `[` … `]`.
   - For variable arguments, prefix with `...`. For literal-array arguments, inline them.
4. If any argument is `null` (or possibly `null`), the spread will throw — guard or coalesce first (`...$maybe ?? []`).

## Before / after

```php
// before
$config = array_merge(
    $this->defaults,
    $this->loadFromFile(),
    $overrides,
);

// after
$config = [
    ...$this->defaults,
    ...$this->loadFromFile(),
    ...$overrides,
];
```

## Stop conditions

- The arguments use **string keys that collide**, and the original code relied on `array_merge` overwriting earlier keys with later ones. Spread does the same for string keys (right-hand wins), so behaviour is preserved — apply the fix.
- The arguments are **integer-keyed and the original code relies on `array_merge` re-numbering keys from 0**. Spread DOES NOT re-number; it preserves keys. If the original arrays had numeric keys that collide, you'll get a different result. **Stop and use `array_merge` if** any operand has integer keys you care about.
- One of the arguments is null/possibly-null. Add `?? []` rather than letting the spread throw.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
composer test
```

Expect zero `array-spread-operator` rows for `<file>` and a green test suite. If a test fails, the most likely cause is integer-key collision behaviour; revert and use `array_merge` with `// @compass-ignore-next-line array-spread-operator`.
