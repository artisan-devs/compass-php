---
name: fix-compass-strict-types-declaration
description: Add `declare(strict_types=1);` to a PHP file that's missing it. Use when Compass reports a `strict-types-declaration` violation.
rule: strict-types-declaration
node_types: []
risk: low
auto_fixable: true
verification: composer compass -- --no-baseline
---

# Fix `strict-types-declaration` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` is always 1 — the violation points at the file as a whole.

## What this rule enforces

Every PHP file must begin with `declare(strict_types=1);` as the first statement after the opening `<?php` tag. No comments, no namespace, no use statements may appear between `<?php` and `declare(strict_types=1);`.

Strict typing prevents PHP's implicit numeric coercion (e.g. `function f(int $x)` silently accepting `'42'`) and matches the rest of the codebase's contract.

## How to apply the fix

1. Open `<file>`.
2. Inspect the first ~5 lines. There are three common starting shapes:
   - `<?php` followed by an empty line, then `namespace ...;` — insert the declare.
   - `<?php` followed by a docblock or comment — move the declare to come right after `<?php` (before the comment) OR keep the comment and insert the declare on the line that immediately follows the opening tag, before any other code.
   - `<?php` followed directly by code — insert the declare after `<?php` on its own line.
3. The required form is exact:
   ```php
   declare(strict_types=1);
   ```
   Spacing inside the parens is optional but the parser is happy with the canonical form above.
4. Leave a single blank line between `declare(strict_types=1);` and whatever follows (namespace, class, expression).
5. Do NOT modify any other code.

## Before / after

```php
// before
<?php

namespace App\Domain\Order;

final class Order
{
}
```

```php
// after
<?php

declare(strict_types=1);

namespace App\Domain\Order;

final class Order
{
}
```

## Stop conditions

- The file is empty or has no `<?php` tag — skip; this rule does not apply.
- The file already has `declare(strict_types=1);` further down (e.g. after a comment block). Move it to the top — it must be the first statement.
- The file has `declare(strict_types=0);` or `declare(ticks=...)`. Replace `strict_types=0` with `strict_types=1`; for `ticks`, add a separate `declare(strict_types=1);` immediately after `<?php` on its own line.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
```

Expect zero `strict-types-declaration` rows for `<file>`.
