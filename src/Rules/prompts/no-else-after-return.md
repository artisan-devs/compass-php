---
name: fix-compass-no-else-after-return
description: Flatten if/else chains where the if branch terminates control flow (return, throw, exit, continue, break). Use when Compass reports a `no-else-after-return` violation.
rule: no-else-after-return
node_types: [PhpParser\Node\Stmt\If_]
risk: low
auto_fixable: true
verification: composer compass -- --no-baseline && composer test
---

# Fix `no-else-after-return` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` points at the `else` or `elseif` keyword that should be removed.

## What this rule enforces

When an `if` branch always exits the surrounding scope (`return`, `throw`, `exit`/`die`, `continue`, `break`), the `else`/`elseif` that follows is redundant. Code following the `if` is unreachable from the terminating branch and can be dedented one level.

Flattening reduces nesting, makes the early-exit pattern visible, and removes a class of bugs where adding a new branch silently shadows the terminator.

## How to apply the fix

1. Open `<file>` at line `<line>`. The line is the `else` or `elseif` keyword.
2. Read upward to confirm the preceding `if` body ends with one of: `return`, `throw`, `exit(...)` / `die(...)`, `continue`, `break`. If it doesn't, do NOT proceed — the rule fired on something it shouldn't have; report and skip.
3. Apply one of the two transforms:

### Case A: `if/else`

Remove the `else` and dedent its body one level.

```php
// before
if ($order === null) {
    return $this->emptyResponse();
} else {
    return new OrderResponse($order);
}

// after
if ($order === null) {
    return $this->emptyResponse();
}

return new OrderResponse($order);
```

### Case B: `if/elseif/.../else`

Convert the `elseif` chain into a sequence of independent `if` blocks (each guarded by the same condition that was on the original `elseif`). The final `else` becomes the trailing fall-through.

```php
// before
if ($x < 0) {
    throw new InvalidArgumentException(message: 'negative');
} elseif ($x === 0) {
    return Order::empty();
} elseif ($x < 10) {
    return Order::small($x);
} else {
    return Order::large($x);
}

// after
if ($x < 0) {
    throw new InvalidArgumentException(message: 'negative');
}

if ($x === 0) {
    return Order::empty();
}

if ($x < 10) {
    return Order::small($x);
}

return Order::large($x);
```

## Stop conditions

- The `if` body assigns to a variable that the `else` body reads after assignment (defensive `$x = ...; else { use $x; }`). The `if` would have to be reorganised first; report and skip.
- The terminating statement is conditional inside the `if` body (e.g. `if (...) { if (...) return; }`). The outer `if` is NOT actually terminating — the rule should not have fired. Report and skip.
- The `else` block contains code that depends on a side effect of the `if` block (e.g. closing a resource). Hoist the side effect out of both branches first, then apply the rule.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
composer test
```

Expect zero `no-else-after-return` rows for `<file>` and green tests. The transformation is purely structural — if tests fail, the rule probably misfired; revert and report.
