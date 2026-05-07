<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

final class BuiltInRules
{
    /**
     * Map of public rule short name (Rule::name()) to its FQCN.
     *
     * Keys are what users put in compass.yaml under `rules:` and what the
     * reporters surface in violation rows. Adding a new built-in rule means
     * adding it here AND making sure its name() returns the matching key.
     *
     * @var array<string, class-string<Rule>>
     */
    public const MAP = [
        'named-arguments' => NamedArgumentsRule::class,
        'named-method-arguments' => NamedMethodArgumentsRule::class,
        'promoted-properties' => PromotedPropertiesRule::class,
        'strict-types-declaration' => StrictTypesDeclarationRule::class,
        'final-classes' => FinalClassesRule::class,
        'no-else-after-return' => NoElseAfterReturnRule::class,
        'typed-declarations' => TypedDeclarationsRule::class,
        'no-service-location' => NoServiceLocationRule::class,
        'use-str-contains' => UseStrContainsRule::class,
        'first-class-callable' => FirstClassCallableRule::class,
        'use-array-spread' => UseArraySpreadRule::class,
        'typed-class-constants' => TypedClassConstantsRule::class,
        'use-match-expression' => UseMatchExpressionRule::class,
        'never-return-type' => NeverReturnTypeRule::class,
        'readonly-classes' => ReadonlyClassesRule::class,
        'numeric-separator' => NumericSeparatorRule::class,
    ];

    /**
     * Built-in rules grouped by intent. Every rule in {@see self::MAP} appears in exactly one category.
     *
     * - **type-safety**: Enforce explicit native PHP types end-to-end.
     * - **modern-php**: Leverage PHP 8+ syntax for clarity at call and declaration sites.
     * - **code-hygiene**: Small readability and simplicity improvements.
     * - **architecture**: Enforce design boundaries between layers.
     *
     * @var array<string, list<string>>
     */
    public const CATEGORIES = [
        'type-safety' => [
            'strict-types-declaration',
            'typed-declarations',
            'typed-class-constants',
            'never-return-type',
        ],
        'modern-php' => [
            'named-arguments',
            'named-method-arguments',
            'promoted-properties',
            'use-str-contains',
            'first-class-callable',
            'use-array-spread',
            'use-match-expression',
            'readonly-classes',
        ],
        'code-hygiene' => [
            'no-else-after-return',
            'final-classes',
            'numeric-separator',
        ],
        'architecture' => [
            'no-service-location',
        ],
    ];

    /**
     * Resolve a configuration entry to a rule FQCN.
     *
     * - Strings containing a backslash are treated as fully-qualified PHP class names.
     * - Bare identifiers must be a key in {@see self::MAP}.
     *
     * @return class-string<Rule>
     *
     * @throws \RuntimeException When the entry is not a known built-in name and not a class implementing Rule.
     */
    public static function resolve(string $entry): string
    {
        if (str_contains($entry, '\\')) {
            $class = ltrim($entry, '\\');
            if (! class_exists($class) || ! is_subclass_of($class, Rule::class)) {
                throw new \RuntimeException(sprintf(
                    'Configured rule "%s" is not a class implementing %s.',
                    $entry,
                    Rule::class,
                ));
            }

            /** @var class-string<Rule> $class */
            return $class;
        }

        if (! isset(self::MAP[$entry])) {
            throw new \RuntimeException(sprintf(
                'Unknown built-in rule "%s". Available: %s. To use a custom rule, pass its fully-qualified class name (must contain a backslash).',
                $entry,
                implode(', ', array_keys(self::MAP)),
            ));
        }

        return self::MAP[$entry];
    }
}
