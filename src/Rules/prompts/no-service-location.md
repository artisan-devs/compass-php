---
name: fix-compass-no-service-location
description: Replace service-locator calls (app(), resolve(), App::make()) inside Domain/Application layers with constructor-injected dependencies. Use when Compass reports a `no-service-location` violation.
rule: no-service-location
node_types: [PhpParser\Node\Expr\FuncCall, PhpParser\Node\Expr\StaticCall]
risk: medium
auto_fixable: true
verification: composer compass -- --no-baseline && composer test
---

# Fix `no-service-location` violations

You are fixing one Compass violation. The row contains `file`, `line`, `rule`, and `message`. The `line` points at the call site of a service-locator function or static method (`app(...)`, `resolve(...)`, `App::make(...)`, `Container::make(...)`).

## What this rule enforces

Domain and Application layer code must declare its dependencies in the constructor. Service location hides what a class needs from its signature: callers, tests, and refactoring tools all see a class that "magically" pulls from the container at runtime, with no compile-time guarantee that the binding exists.

The rule fires only inside files whose path contains `/Domain/` or `/Application/`. Infrastructure-layer files (controllers, providers, factories) are exempt because they're allowed to wire the container.

## How to apply the fix

1. Open `<file>` at line `<line>`. Identify the resolved type:
   - `app(SomeClass::class)` → resolves `SomeClass`.
   - `app()->make(SomeClass::class)` → resolves `SomeClass`.
   - `resolve(SomeClass::class)` → resolves `SomeClass`.
   - `App::make(SomeClass::class)` → resolves `SomeClass`.
   - `app('alias')` / `resolve('alias')` → string-keyed binding. Find the underlying class by reading the matching `bind`/`singleton` call in a service provider.

2. Locate the constructor of the enclosing class. If it doesn't exist, add one.

3. Add a promoted constructor parameter for the resolved type. Pick the most specific type — prefer the **interface** if one exists (e.g. `OrderRepositoryInterface` over `EloquentOrderRepository`), so the class stays decoupled from infrastructure.

   ```php
   public function __construct(
       private readonly OrderRepositoryInterface $orders,
       // ... existing dependencies
   ) {
   }
   ```

4. Replace the service-locator call site with `$this->{name}` (or the field name you chose).

5. Update tests that instantiate the class. Constructor-injection makes the new dependency visible — they'll need to pass a real or mock instance.

## Before / after

```php
// before
final class CreateOrderUseCase
{
    public function execute(CreateOrderRequest $request): CreateOrderResponse
    {
        $repository = app(OrderRepositoryInterface::class);
        $repository->save(order: $request->toOrder());

        return CreateOrderResponse::created();
    }
}
```

```php
// after
final class CreateOrderUseCase
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
    ) {
    }

    public function execute(CreateOrderRequest $request): CreateOrderResponse
    {
        $this->repository->save(order: $request->toOrder());

        return CreateOrderResponse::created();
    }
}
```

## Stop conditions

- The call resolves a string alias whose backing class you can't determine. Don't guess — locate the binding (`grep -rn "bind\|singleton" src/Infrastructure/Providers/ | grep '<alias>'`) before injecting.
- The class is a Domain Event listener or Symfony-style messenger handler whose framework wires it via reflection. Confirm the framework's contract supports constructor injection for that role.
- The container call resolves a contextual binding (e.g. `app(Logger::class, ['channel' => 'orders'])`). Constructor injection alone won't replicate the contextual argument — leave the call, document why, and add `// @compass-ignore-next-line no-service-location` with the rationale.
- The file's path doesn't actually contain `/Domain/` or `/Application/`. The rule should not have fired; report and skip.

## Verification

```bash
composer compass -- --no-baseline 2>&1 | grep '<file>'
composer test
```

Expect zero `no-service-location` rows for `<file>` and green tests. If a test fails because the class now needs a constructor argument, update the test to inject the dependency (use the same interface and a mock or the real implementation).
