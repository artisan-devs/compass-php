---
name: fix-compass-promoted-properties
description: Refactor a class to use PHP 8 constructor property promotion. Use when Compass reports a `promoted-properties` violation, which means a constructor parameter mirrors a same-named class property and is assigned via `$this->X = $X;`.
rule: promoted-properties
node_types: [PhpParser\Node\Stmt\Class_]
risk: medium
auto_fixable: true
verification: composer compass -- --no-baseline && composer test
---

# Fix `promoted-properties` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` points at the constructor parameter that should be promoted; the property declaration and the assignment statement are elsewhere in the same class.

## What this rule enforces

A constructor parameter is "promotable" when **all three** of the following hold:

1. The class declares a property with the same name as the parameter (`private FOO $name;`).
2. The constructor's parameter has the same name and is not already promoted (no visibility modifier).
3. The constructor body contains a trivial assignment `$this->name = $name;` — exactly that shape, not transformed (`strtolower($name)` etc.).

When all three are true, the rule fires and the fix collapses the three pieces into a single promoted parameter.

## How to apply the fix

1. Open `<file>`. Note the parameter name on `<line>` — call it `$name` below.
2. Locate the matching property declaration somewhere in the class body. Capture its visibility (`private`/`protected`/`public`), readonly-ness, type, and any default value or attributes.
3. Locate the matching assignment `$this->name = $name;` in `__construct`.
4. Rewrite as a single promoted parameter on the constructor:
   - Add the property's visibility modifier.
   - Add `readonly` if the property was declared `readonly`, OR if the class semantically benefits from immutability (this is a judgement call — for value objects, entities, and use cases, prefer `readonly`; for mutable services, keep mutable).
   - Preserve the type hint exactly as on the property.
   - Preserve any attributes on the property by hoisting them onto the parameter.
5. **Delete** the original property declaration line.
6. **Delete** the matching `$this->name = $name;` assignment from `__construct`.
7. Repeat for every parameter in the same constructor that has its own violation row, then leave the rest alone.

## Before / after

```php
// before
final class CreateOrderUseCase
{
    private OrderRepositoryInterface $orderRepository;
    private ProductValidator $productValidator;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ProductValidator $productValidator,
    ) {
        $this->orderRepository = $orderRepository;
        $this->productValidator = $productValidator;
    }
}

// after
final class CreateOrderUseCase
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductValidator $productValidator,
    ) {
    }
}
```

## Stop conditions

- The property has a non-trivial default value (e.g. `private array $items = [];`). Promotion still works (`private array $items = []`) but if the class also assigns the param conditionally, do NOT promote — leave it.
- The property has additional setter methods (`setName($name)` writes to `$this->name`). Promotion is still valid as long as the property isn't `readonly`. If you add `readonly` and a setter exists, you'll break the setter — either keep the property mutable or remove the setter (only with explicit user approval).
- The property is documented with a complex `@var` PHPDoc that the typed declaration does not capture (e.g. `@var list<NonEmptyString>`). Hoist the PHPDoc onto the parameter — do not silently drop it.
- The class also has a `__sleep` / `__wakeup` / `__serialize` that references the property name as a string. Promotion preserves the property name, so this still works. No action needed.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
composer test -- --filter=$(basename '<file>' .php)
```

Expect zero `promoted-properties` violations for `<file>` and a green test for any class whose tests cover this code. If tests break, the most likely cause is that you added `readonly` where the class actually mutates the property — either drop `readonly` or fix the mutation.
