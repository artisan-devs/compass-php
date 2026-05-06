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
