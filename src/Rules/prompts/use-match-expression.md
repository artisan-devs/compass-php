---
name: fix-compass-use-match-expression
description: Convert switch statements with no fall-through into match expressions. Use when Compass reports a `use-match-expression` violation.
rule: use-match-expression
node_types: [PhpParser\Node\Stmt\Switch_]
risk: medium
auto_fixable: true
verification: composer compass -- --no-baseline && composer test
---

# Fix `use-match-expression` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` points at the `switch` keyword.

## What this rule enforces

Switch statements that look up a value and produce a single result (no fall-through, every case terminates) are clearer as `match` expressions. Match:

- Uses strict comparison (`===`) so type-juggled bugs go away.
- Returns a value, so the surrounding `$x = …;` boilerplate disappears.
- Is exhaustive (throws `UnhandledMatchError` on missing default) — bugs become loud.
- Allows comma-grouping cases.

## How to apply the fix

1. Open `<file>` at line `<line>`. Read the whole switch.
2. Decide between two shapes:
   - **Each case assigns to the same variable, then the variable is used after the switch** → wrap match in `$var = match (…) { … };`.
   - **Each case `return`s** → return the match expression directly: `return match (…) { … };`.
3. Convert each case body. Three sub-cases:
   - Single `return $expr;` → `case_value => $expr,`
   - Single `$var = $expr; break;` → `case_value => $expr,`
   - Single `throw …` → `case_value => throw …,`
4. Group consecutive cases that share the same body using comma-separated case values: `1, 2, 3 => 'low'`.
5. Convert `default:` to `default => …,`.
6. If there's no `default` AND the switch's value is an enum or has a known finite range, omit the default — match's exhaustiveness checking will throw `UnhandledMatchError` on unexpected values, which is what you want. If the value is open-ended (user input), KEEP the default arm.

## Before / after

```php
// before
switch ($status) {
    case 'pending':
    case 'processing':
        $label = 'In flight';
        break;
    case 'shipped':
        $label = 'On its way';
        break;
    case 'delivered':
        $label = 'Done';
        break;
    default:
        throw new InvalidArgumentException("Unknown status {$status}");
}
return $label;

// after
return match ($status) {
    'pending', 'processing' => 'In flight',
    'shipped' => 'On its way',
    'delivered' => 'Done',
    default => throw new InvalidArgumentException("Unknown status {$status}"),
};
```

## Stop conditions

- A case body is **multi-statement** (e.g. logs AND assigns AND breaks). Match arm values are single expressions. Either extract to a private method and call it from the arm, or skip the conversion.
- A case body **falls through to the next** (`case 'a': case 'b': $x = …; break;` is fine — comma-group them; but `case 'a': do_something(); /* no break */ case 'b': do_other();` is intentional fall-through and CANNOT be expressed in match).
- The switch performs **side-effects on every case** that aren't suitable as expression values. Keep it as a switch.
- The switch's value is **loosely compared** (e.g. relying on `0 == 'foo'` matching). Match uses `===`; switching to match changes semantics. Verify with tests before applying.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
composer test
```

Expect zero `use-match-expression` rows for `<file>` and green tests. If tests fail, suspect a loose-comparison change.
