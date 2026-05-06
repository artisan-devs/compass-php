<?php

declare(strict_types=1);

namespace Sidetours\Compass\Engine;

use Sidetours\Compass\Rules\BuiltInRules;
use Sidetours\Compass\Rules\Rule;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class Configuration
{
    /**
     * @param list<string>             $paths     Absolute or project-relative paths to scan.
     * @param list<string>             $exclude   Path globs to exclude.
     * @param list<Rule>               $rules     Activated rule instances.
     * @param array<string, list<string>> $ignore  Map of file glob => list of rule names ('*' = all).
     * @param string|null              $baseline  Absolute path to a baseline file, or null.
     */
    public function __construct(
        public string $projectRoot,
        public array $paths,
        public array $exclude,
        public array $rules,
        public array $ignore,
        public ?string $baseline,
    ) {
    }

    public static function load(string $configFile, string $projectRoot): self
    {
        if (! is_file($configFile)) {
            throw new \RuntimeException(sprintf('Compass config file not found: %s', $configFile));
        }

        $contents = (string) file_get_contents($configFile);
        try {
            $raw = Yaml::parse($contents);
        } catch (ParseException $e) {
            throw new \RuntimeException(sprintf('Compass config %s is not valid YAML: %s', $configFile, $e->getMessage()), previous: $e);
        }
        if (! is_array($raw)) {
            throw new \RuntimeException(sprintf('Compass config must be a YAML mapping, got %s', get_debug_type($raw)));
        }

        $rules = [];
        foreach ((array) ($raw['rules'] ?? []) as $entry) {
            if (! is_string($entry) || $entry === '') {
                throw new \RuntimeException('Each entry under "rules" must be a non-empty string (built-in name or PHP FQCN).');
            }
            $class = BuiltInRules::resolve($entry);
            $rules[] = new $class();
        }

        $baseline = null;
        if (isset($raw['baseline']) && is_string($raw['baseline']) && $raw['baseline'] !== '') {
            $baseline = self::resolvePath($raw['baseline'], $projectRoot);
        }

        return new self(
            projectRoot: $projectRoot,
            paths: array_values(array_map(static fn (string $p) => self::resolvePath($p, $projectRoot), (array) ($raw['paths'] ?? []))),
            exclude: array_values((array) ($raw['exclude'] ?? [])),
            rules: $rules,
            ignore: (array) ($raw['ignore'] ?? []),
            baseline: $baseline,
        );
    }

    private static function resolvePath(string $path, string $projectRoot): string
    {
        if ($path === '') {
            return $projectRoot;
        }
        if ($path[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        return rtrim($projectRoot, '/').'/'.$path;
    }
}
