---
name: fix-compass-named-arguments
description: Convert positional arguments at any callsite — constructor invocations, method calls, nullsafe method calls, or static calls — into named arguments. Use this when Compass reports a `named-arguments` violation.
rule: named-arguments
node_types: [PhpParser\Node\Expr\New_, PhpParser\Node\Expr\MethodCall, PhpParser\Node\Expr\NullsafeMethodCall, PhpParser\Node\Expr\StaticCall]
risk: low
auto_fixable: true
verification: composer compass -- --no-baseline
---

# Fix `named-arguments` violations

You are fixing one Compass violation. The caller passes you a row containing `file`, `line`, `rule`, and `message`. Apply the recipe below to that single location and stop.

## What this rule enforces

Every callsite where named arguments are syntactically permitted must use them for every argument. Four call shapes are in scope:

1. `new ClassName(...)` — FQN, aliased import.
2. `parent::__construct(...)`, `self::__construct(...)`, `static::__construct(...)`.
3. `$obj->method(...)` and `$obj?->method(...)` — instance method calls (including nullsafe).
4. `ClassName::staticMethod(...)` — non-constructor static calls.

Plain function calls (`strpos($h, $n)`, `array_map($cb, $arr)`) are **out of scope** — they're rarely worth named-arg pressure. Anonymous-class literals (`new class { ... }`) are also out of scope.

## How to apply the fix

1. Open `<file>` at line `<line>` and read the surrounding context (~5 lines above and below).
2. Identify the callsite. The violation `message` tells you which one (e.g. `Positional argument passed to ->addCommand()` vs. `new CheckCommand()` — if both are on one line, fix both in one pass).
3. Resolve the target signature:
   - **Constructor** (`new Foo(...)` / `parent::__construct(...)`): read the class's `__construct` parameters; follow `use` aliases at the top of the file.
   - **Method call** (`$obj->method(...)` / `$obj?->method(...)`): infer the receiver type from a property declaration, parameter type, or constructor injection; read that class's method signature.
   - **Static call** (`Foo::method(...)`): follow the class name; read the static method's signature.
   - If the target class is in `vendor/`, you may open it read-only to inspect the signature.
4. For each positional argument, prefix it with the corresponding parameter name. Example: `'alice@example.com'` becomes `email: 'alice@example.com'`.
5. Keep the original argument order to minimise diff churn (named arguments allow reordering, but reordering is not required).
6. Do NOT modify:
   - `...$args` variadic spread arguments — leave them positional.
   - Arguments that already have a `name:` prefix.
   - `new class { ... }` anonymous-class literals.
   - Plain function calls — they're not the target of this rule.
   - Calls inside `vendor/` files — fix only first-party code.

## Before / after

```php
// before
return new Order($id, $customer, new Money(1000, 'USD'));
$this->addCommand(new CheckCommand($projectRoot));
$clock?->modify('+1 day');
DateTimeImmutable::createFromFormat('Y-m-d', '2026-01-01');

// after
return new Order(
    id: $id,
    customer: $customer,
    total: new Money(amount: 1000, currency: 'USD'),
);
$this->addCommand(command: new CheckCommand(projectRoot: $projectRoot));
$clock?->modify(modifier: '+1 day');
DateTimeImmutable::createFromFormat(format: 'Y-m-d', datetime: '2026-01-01');
```

## Stop conditions

- The target signature cannot be statically resolved (dynamic class name, dynamic method name, expression-driven receiver, missing source). Report the violation as needing human review and move on — do not guess parameter names.
- The call uses `func_get_args()`, `ReflectionMethod::getParameters()`, or another reflection trick that depends on argument order. Skip and flag for review.
- The target method is in a third-party API where parameter names are part of the public contract you do not control (some Symfony / Doctrine internals). Confirm the names against the upstream docs before naming.

## Verification

After editing, run:

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
```

Expect zero `named-arguments` lines for `<file>`. If new violations appear (parser error, broken syntax), revert and ask for help.
