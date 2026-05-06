# Compass

Compass is a small, opinionated architecture-and-style-rules engine for PHP codebases. It walks your sources with `nikic/php-parser`, runs a configurable set of AST-aware rules — currently `named-arguments`, `named-method-arguments`, and `promoted-properties` — and reports each drift from the agreed path. A `composer compass` invocation produces text, JSON, or GitHub-Actions output, grouped by file or by rule. Adoption is incremental: `composer compass:baseline` snapshots existing violations into a fingerprint file so new violations fail CI immediately, while pre-existing ones stay quietly tracked until you fix them. Inline `// @compass-ignore`, file-wide `@compass-ignore-file`, and glob-based suppressions in `compass.php` cover the cases where the rule shouldn't apply.

What sets Compass apart from existing PHP architecture tools is that every rule ships with an AI-ready fix prompt — YAML frontmatter plus a markdown body — exposed via `composer compass:prompts`. The prompt for each rule encodes its detection algorithm, the canonical refactor recipe, the edge cases the agent must respect, and the verification command to run afterward. That turns each violation row from "something a human has to triage" into a concrete instruction packet that pairs with file-and-line metadata to drive an autonomous fix loop. The result is a rules engine that doesn't just tell you the codebase has drifted off course — it hands the next agent a turn-by-turn route back.

## Quick start

```bash
composer require --dev sidetours/compass
```

Create a `compass.php` at the project root:

```php
<?php

use Sidetours\Compass\Rules\NamedArgumentsRule;
use Sidetours\Compass\Rules\NamedMethodArgumentsRule;
use Sidetours\Compass\Rules\PromotedPropertiesRule;

return [
    'paths' => ['src', 'tests'],
    'exclude' => ['src/Generated'],
    'rules' => [
        NamedArgumentsRule::class => [],
        NamedMethodArgumentsRule::class => [],
        PromotedPropertiesRule::class => [],
    ],
    'ignore' => [
        // 'src/Legacy/**' => ['*'],
    ],
    'baseline' => 'compass-baseline.php',
];
```

Add to `composer.json`:

```json
"scripts": {
  "compass": "@php vendor/bin/compass check",
  "compass:baseline": "@php vendor/bin/compass baseline",
  "compass:prompts": "@php vendor/bin/compass prompts"
}
```

Run:

```bash
composer compass:baseline      # snapshot current state
composer compass                # check for new violations
composer compass -- --no-baseline --group-by=rule   # review what's suppressed
composer compass:prompts -- --out=docs/compass-prompts/   # export fix prompts
```

## CLI

| Command | Purpose |
|---|---|
| `compass check` | Default. Runs all rules; honours baseline and ignores. |
| `compass baseline` | Writes the current set of violation fingerprints to the configured baseline file. |
| `compass prompts` | Prints (or with `--out=DIR`, writes) one fix-prompt markdown file per registered rule. |

`check` options:

- `--reporter=text\|json\|github` — output format.
- `--group-by=file\|rule` (text + json) or `none` (json only) — grouping in the output.
- `--no-baseline` — ignore the configured baseline; report every violation.
- `--config=PATH` — alternate config file.

## Inline suppressions

```php
$x = new \DateTimeImmutable('now'); // @compass-ignore named-arguments
// @compass-ignore-next-line named-method-arguments
$y = $clock->modify('+1 day');
```

File-wide:

```php
<?php
// @compass-ignore-file named-arguments
```

Omit the rule list to silence every Compass rule for that location.

## Built-in rules

| Rule | Catches |
|---|---|
| `named-arguments` | Positional args at constructor invocations (`new Foo(...)`, `parent::__construct(...)`). |
| `named-method-arguments` | Positional args at method/static calls (`$x->y()`, `Foo::bar()`). Plain function calls are out of scope. |
| `promoted-properties` | Class properties that mirror an unpromoted constructor parameter assigned via `$this->X = $X;`. |

Each rule has a sidecar prompt file under `src/Rules/prompts/<rule>.md`.

## Adding a rule

Implement `Sidetours\Compass\Rules\Rule`, return the PhpParser node FQCNs you want to inspect from `nodeTypes()`, yield `Violation`s in `check()`, and return a YAML-frontmatter prompt from `fixPrompt()`. Register the class in your project's `compass.php` under `'rules'`.

## Testing this package

```bash
composer install
composer test
composer phpstan
```
