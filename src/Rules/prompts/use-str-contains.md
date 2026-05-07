---
name: fix-compass-use-str-contains
description: Replace strpos() comparisons with str_contains / str_starts_with. Use when Compass reports a `use-str-contains` violation.
rule: use-str-contains
node_types: [PhpParser\Node\Expr\BinaryOp\Identical, PhpParser\Node\Expr\BinaryOp\NotIdentical]
risk: low
auto_fixable: true
verification: composer compass -- --no-baseline && composer test
---

# Fix `use-str-contains` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` points at the `===`/`!==` comparison.

## What this rule enforces

Replace legacy `strpos`-based substring checks with the dedicated PHP 8.0 functions, which read more clearly and avoid the strict-comparison-with-false footgun:

- `strpos($h, $n) !== false` → `str_contains($h, $n)`
- `strpos($h, $n) === false` → `! str_contains($h, $n)`
- `strpos($h, $n) === 0` → `str_starts_with($h, $n)`

## How to apply the fix

1. Open `<file>` at line `<line>`.
2. Identify which shape the comparison takes (see above) and rewrite to the matching function.
3. Preserve argument order: `strpos($haystack, $needle)` → `str_contains($haystack, $needle)`. Same for `str_starts_with`.
4. Drop the now-redundant comparison entirely (no need for `=== true`).

## Before / after

```php
// before
if (strpos($email, '@') !== false) { /* ... */ }
if (strpos($path, '/api/') === false) { /* ... */ }
if (strpos($name, 'admin_') === 0) { /* ... */ }

// after
if (str_contains($email, '@')) { /* ... */ }
if (! str_contains($path, '/api/')) { /* ... */ }
if (str_starts_with($name, 'admin_')) { /* ... */ }
```

## Stop conditions

- The original code uses the *position* returned by `strpos` for slicing (e.g. `substr($s, 0, strpos($s, '@'))`). That's not a containment check — leave it.
- The needle could be the empty string. `strpos($s, '') !== false` is always true; `str_contains($s, '')` is also always true. Behaviour is identical, fix is safe.
- The comparison is `=== false` AND the haystack/needle types aren't strings (`?string`). Add a null guard before the call rather than relying on the old `strpos` returning `false` for non-strings.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
composer test
```

Expect zero `use-str-contains` rows for `<file>` and a green test suite.
