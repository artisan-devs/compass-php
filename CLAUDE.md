# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

`sidetours/compass` — a standalone PHP 8.4 library (not a Laravel app) that provides an AST-based architecture/style rules engine for PHP codebases. Walks sources with `nikic/php-parser`, runs configurable rules, and reports violations as text/JSON/GitHub Actions output. Distinguishing feature: every rule ships an AI-ready fix prompt (YAML frontmatter + markdown) so violations can be handed to a downstream agent for autonomous fixing.

Distributed via the private Satis registry at `packages-dev-sidetours.excelia.com`. Consumed by sibling backend microservices as a `--dev` Composer dependency.

## Commands

```bash
composer install            # PHP 8.4 + nikic/php-parser ^5 + symfony/console ^7
composer test               # PHPUnit 11 (phpunit.xml — bootstraps vendor/autoload.php)
composer phpstan            # phpstan analyse src --level=5

# Single test
vendor/bin/phpunit --filter=test_name
vendor/bin/phpunit tests/Engine/RunnerTest.php

# Run the CLI against a host project (cwd is treated as projectRoot)
vendor/bin/compass check               # default command; honours baseline + ignores
vendor/bin/compass baseline            # snapshot current violations to configured file
vendor/bin/compass prompts --out=DIR   # export per-rule fix prompts as markdown

vendor/bin/compass check --reporter=text|json|github|html --group-by=file|rule|none
vendor/bin/compass check --reporter=html --out=build/compass  # multi-page report (--out is required for html)
vendor/bin/compass check --no-baseline  # ignore baseline; report everything
```

`check` exits 0 (clean), 1 (violations), or 2 (errors / config problem). `bin/compass` looks for autoload at `../vendor/autoload.php` (standalone) or `../../../autoload.php` (installed under `vendor/sidetours/compass/`).

## Architecture

The pipeline is `Configuration → FileScanner → Runner → RuleVisitor (AST) → Rule::check → Violation → IgnoreList → Reporter`. Each piece lives under `src/`:

- **`Engine/Configuration`** — loads `compass.yaml` (parsed via `symfony/yaml`). Resolves paths relative to `projectRoot`, instantiates registered `Rule` classes via `Rules\BuiltInRules::resolve()`, and resolves the optional baseline path. Required keys: `paths`, `rules` (each entry is either a built-in short name or a custom FQCN — the backslash is the discriminator). Optional: `exclude`, `ignore` (glob → rule list, `'*'` = all), `baseline`. JSON Schema lives at `compass.schema.json` at the package root.

- **`Engine/Runner`** — the orchestrator. Builds a `class-string<Node> => list<Rule>` index up front (`indexRulesByNodeType`) so each AST node only dispatches to interested rules. For every file: read → parse → traverse with a single `RuleVisitor` → filter collected violations through `Context` (inline annotations) and `IgnoreList` (globs + baseline fingerprints).

- **`Engine/RuleVisitor`** — `NodeVisitorAbstract`. On each node, looks up matching rules by node FQCN and yields their `check()` results into `collected`. One traversal per file, regardless of rule count.

- **`Engine/Context`** — per-file. At construction, parses the source for `@compass-ignore`, `@compass-ignore-next-line`, and `@compass-ignore-file` comments (with optional comma-separated rule lists). `isIgnored(rule, line)` is consulted by the Runner before keeping a violation.

- **`Engine/IgnoreList`** — combines two suppression mechanisms: (1) glob patterns from `compass.yaml` `ignore:` and (2) baseline fingerprints loaded from the configured baseline file. Fingerprint = `sha1(rule|file|line|message)` (`Violation::fingerprint`). Custom glob → regex implementation in `globToRegex` (supports `*`, `**`, `?`). Note: the baseline file itself is still PHP (auto-generated `var_export`), not YAML — it's an internal data file, never hand-edited.

- **`Engine/FileScanner`** — recursive `.php` discovery. Excludes match against both relative and absolute paths via `fnmatch`. Plain-string excludes (no glob) are treated as directory prefixes.

- **`Rules/Rule`** (interface) — `name()`, `shortDescription()`, `nodeTypes(): list<class-string<Node>>`, `check(Node, Context): iterable<Violation>`, `fixPrompt(): string`. Built-in rules are grouped by intent in `Rules/BuiltInRules::CATEGORIES`:
  - **type-safety** — `strict-types-declaration`, `typed-declarations`, `typed-class-constants` (8.3+), `never-return-type` (8.1+)
  - **modern-php** — `named-arguments`, `named-method-arguments`, `promoted-properties`, `use-str-contains` (8.0+), `first-class-callable` (8.1+), `use-array-spread` (7.4+), `use-match-expression` (8.0+), `readonly-classes` (8.2+)
  - **code-hygiene** — `no-else-after-return`, `final-classes`, `numeric-separator` (7.4+)
  - **architecture** — `no-service-location`

  Each rule loads its prompt from a sidecar `src/Rules/prompts/<rule-name>.md`. The short-name registry lives in `Rules/BuiltInRules::MAP`; categories live in `Rules/BuiltInRules::CATEGORIES` and are mirrored in `compass.schema.json` (one `oneOf` branch per category, so `yaml-language-server` autocomplete shows the same grouping in editors). Guard test (`tests/Rules/BuiltInRulesTest.php`) asserts every Rule subclass under `src/Rules/` is in `MAP` AND in exactly one `CATEGORIES` bucket with a key matching `name()`.

- **`Rules/FileRule`** (companion interface) — for whole-file checks that don't fit the per-node visitor model (e.g. detecting a *missing* top-level `declare(strict_types=1);`). The Runner calls `checkFile(Context)` once per file alongside AST traversal. Implementing classes still implement `Rule` (so they appear in reports under their `name()` and ship a `fixPrompt()`); they typically return `[]` from `nodeTypes()`. Currently used by `StrictTypesDeclarationRule`.

- **`Reporters/`** — `TextReporter`, `JsonReporter`, `GithubActionsReporter`, `HtmlReporter` all implement `Reporter::report(Result, Output, projectRoot)`. `HtmlReporter` requires `--out=DIR` and emits a multi-page navigable report (index + one page per rule + one page per file with line-by-line source highlighting); its static assets (`styles.css`, `app.js`) live at `src/Reporters/html/` and are copied into `<out>/assets/` on each run.

- **`Cli/Application`** — Symfony Console. Constructed with `getcwd()` as `projectRoot`. `check` is the default command.

### Adding a rule

1. Implement `Rules\Rule`. Return the PhpParser node FQCNs from `nodeTypes()` — the visitor only invokes you for those.
2. In `check()`, yield `Violation`s. Use `$context->file`, the node's `getLine()`, and a stable, descriptive message (the message participates in the baseline fingerprint, so changing wording invalidates baselines).
3. Write a prompt at `src/Rules/prompts/<rule-name>.md` with YAML frontmatter (`name`, `description`, `rule` minimum) plus a markdown body covering detection, refactor, edge cases, verification. Load it from `fixPrompt()`.
4. If shipping the rule as a built-in: register it in `Rules\BuiltInRules::MAP` (key = `name()`, value = FQCN), add it to one of the `CATEGORIES` buckets, and extend the matching per-category `enum` array under `compass.schema.json` `rules.items.oneOf`. If host-project-only: register the FQCN in that project's `compass.yaml` under `rules:` (the backslash flags it as custom; categorisation is up to the host project).

### Tests

- `tests/Engine/` — engine-level (Runner, IgnoreList).
- `tests/Rules/` — one test per rule, plus `RuleFixPromptTest` validates every registered rule has a parseable prompt.
- `tests/Cli/` — Symfony Console command tests.
- `tests/Fixtures/<rule>/{passing,failing}/*.php` — each rule has a passing/failing fixture pair the rule test asserts against. When adding a rule, follow this layout. Fixtures are excluded from autoload classmap (`composer.json`).

## Conventions

- `declare(strict_types=1)` on every PHP file.
- `final readonly class` for value types (`Configuration`, `Violation`, `Result`); `final class` elsewhere.
- Constructor property promotion is the norm — and is itself enforced by `PromotedPropertiesRule` against host projects.
- Named arguments at construction sites (also self-enforced by `NamedArgumentsRule`).
- Exit codes are part of the contract: 0 = clean, 1 = violations, 2 = errors. Don't reorder.
- Baseline files are auto-generated PHP returning a sorted, deduplicated `string[]` of fingerprints — never hand-edit.
