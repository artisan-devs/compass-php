---
name: fix-compass-first-class-callable
description: Replace array-callable / string-callable arguments with first-class callable syntax. Use when Compass reports a `first-class-callable` violation. Requires PHP 8.1+.
rule: first-class-callable
node_types: [PhpParser\Node\Expr\FuncCall]
risk: medium
auto_fixable: true
verification: composer compass -- --no-baseline && composer test
---

# Fix `first-class-callable` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` points at the array-literal or string-literal callable inside a callable-consuming function call (`array_map`, `array_filter`, `usort`, …).

## What this rule enforces

PHP 8.1 introduced first-class callable syntax: `Foo::bar(...)`, `$obj->bar(...)`, `func_name(...)`. It's strictly typed (the IDE/PHPStan can resolve the signature) and avoids the type-laundering of `[$obj, 'method']` array-callables.

## How to apply the fix

1. Open `<file>` at line `<line>`. Identify which shape the callable takes:
   - `[$obj, 'method']` → `$obj->method(...)`
   - `[Foo::class, 'method']` → `Foo::method(...)`
   - `'function_name'` → `function_name(...)`
   - `[ParentClass::class, 'method']` (calling a parent's method on `$this`) → leave it; first-class doesn't directly express "parent::"
2. Replace the array/string literal with the first-class form.
3. Preserve namespaces: `'My\\Ns\\helper'` → `\My\Ns\helper(...)` with a `use` import added at the top.

## Before / after

```php
// before
$names = array_map([$this, 'normalize'], $rawNames);
$result = array_filter($items, 'is_string');
usort($orders, [OrderComparator::class, 'compare']);

// after
$names = array_map($this->normalize(...), $rawNames);
$result = array_filter($items, is_string(...));
usort($orders, OrderComparator::compare(...));
```

## Stop conditions

- The first array element is an expression that's not a class-name and not `$this`/`$obj` (e.g. `[someFunctionReturningObj(), 'method']`). The first-class form requires a value, not an arbitrary expression — leave it positional.
- The string callable is a constant or variable (`$callbackName`, `self::CALLBACK`). First-class doesn't express that — leave it.
- The function being passed the callable is a custom one whose contract requires array-callables (e.g. it inspects them with reflection). Leave it.
- The callable references a method that takes default args — first-class callable forwards all args, which is what you want; no change needed.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
composer test
```

Expect zero `first-class-callable` rows for `<file>` and a green test suite.
