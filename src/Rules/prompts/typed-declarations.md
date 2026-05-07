---
name: fix-compass-typed-declarations
description: Add explicit type declarations to properties, parameters, and return types that lack them. Use when Compass reports a `typed-declarations` violation.
rule: typed-declarations
node_types: [PhpParser\Node\Stmt\Property, PhpParser\Node\Param, PhpParser\Node\Stmt\Function_, PhpParser\Node\Stmt\ClassMethod, PhpParser\Node\Expr\Closure, PhpParser\Node\Expr\ArrowFunction]
risk: medium
auto_fixable: true
verification: composer compass -- --no-baseline && composer phpstan && composer test
---

# Fix `typed-declarations` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` points at the property declaration, parameter, or function/method header that lacks a type.

## What this rule enforces

Every property, parameter, and return type must have an explicit native PHP type. PHPDoc `@var` / `@param` / `@return` are NOT a substitute — they're not enforced at runtime and they're invisible to PHP's type system.

The three sub-cases you may receive:

1. **Property** — `private $foo;` → must be `private SomeType $foo;`.
2. **Parameter** — `function f($x)` → must be `function f(SomeType $x)`.
3. **Return type** — `function f()` → must be `function f(): SomeType`. (Constructors and destructors are exempt because they cannot return a value.)

## How to apply the fix

1. Open `<file>` at line `<line>`. Identify which of the three shapes is at fault from the message.
2. Decide the correct type. Three sources, in order of preference:
   - **PHPDoc** (`@var`, `@param`, `@return`) — adopt the type from there if present and accurate. If PHPDoc says `int|null`, write `?int` or `int|null`.
   - **Usage** — find every place the property is assigned, the parameter is passed, or the function is called. Infer the narrowest type that fits all sites.
   - **Domain knowledge** — for entities, value objects, and DTOs, prefer the most specific type (`OrderId` over `string`, `Money` over `int`).
3. Add the type:
   - Property: `<visibility> [readonly] <Type> $name;`
   - Parameter: `<Type> $name`
   - Return: `): <Type> {` (use `: void` for procedures; `: never` for functions that always throw or call exit/die)
4. After adding the type, delete the now-redundant PHPDoc tag (`@var`, `@param`, `@return`) UNLESS it carries information the type system can't express (e.g. `@var list<NonEmptyString>` over `array`). Keep the PHPDoc only when it adds genericity or shape information.

## Before / after

```php
// before — property
class OrderRepository
{
    private $cache;
}

// after
class OrderRepository
{
    private CacheInterface $cache;
}
```

```php
// before — parameter
public function find($id)
{
    return $this->cache->get($id);
}

// after
public function find(OrderId $id): ?Order
{
    return $this->cache->get(key: $id->toString());
}
```

```php
// before — return type
public function place(Order $order)
{
    $this->orders[] = $order;
}

// after
public function place(Order $order): void
{
    $this->orders[] = $order;
}
```

## Stop conditions

- The type is genuinely `mixed` (the property holds anything; the parameter accepts a polymorphic value with no common base). Add `mixed` explicitly and a one-line comment justifying it (`// mixed: arbitrary user-supplied JSON value`).
- The function uses `func_get_args()` or variadic forwarding. Add `mixed ...$args` for the parameter; the return type should still be specified.
- The property is initialised with a constant whose type isn't obvious (e.g. `private $config = [...]` with a complex array shape). Use `array` as the native type AND keep an `@var array<string, ...>` PHPDoc so PHPStan can still narrow it.
- The function is a magic method other than `__construct`/`__destruct`. Look up the contract: `__toString(): string`, `__get(string $name): mixed`, `__call(string $name, array $arguments): mixed`, etc.
- The function is `__invoke` on an invokable use-case — declare the explicit return type that matches what callers expect.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
composer phpstan
composer test
```

Expect zero `typed-declarations` rows for `<file>`, no new PHPStan errors, and green tests. If PHPStan finds new errors after the change, the inferred type was too narrow — widen it (e.g. add `?` for nullables, use a union for polymorphic returns).
