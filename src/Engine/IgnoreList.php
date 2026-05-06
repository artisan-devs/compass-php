<?php

declare(strict_types=1);

namespace Sidetours\Compass\Engine;

final class IgnoreList
{
    /**
     * @param array<string, list<string>> $patterns Map of file glob => list of rule names ('*' = all).
     * @param list<string>                $baseline Violation fingerprints recorded as accepted.
     */
    public function __construct(
        private readonly array $patterns,
        private readonly array $baseline,
        private readonly string $projectRoot,
    ) {
    }

    public static function fromConfiguration(Configuration $config): self
    {
        $baseline = [];
        if ($config->baseline !== null && is_file($config->baseline)) {
            $loaded = require $config->baseline;
            if (is_array($loaded)) {
                $baseline = array_values(array_filter($loaded, 'is_string'));
            }
        }

        return new self($config->ignore, $baseline, $config->projectRoot);
    }

    public function isIgnored(Violation $violation): bool
    {
        if (in_array($violation->fingerprint(), $this->baseline, true)) {
            return true;
        }

        $relative = $this->relativePath($violation->file);
        foreach ($this->patterns as $glob => $rules) {
            if (! self::matches($glob, $relative)) {
                continue;
            }
            if (in_array('*', $rules, true) || in_array($violation->rule, $rules, true)) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $absolute): string
    {
        $root = rtrim($this->projectRoot, '/').'/';
        if (str_starts_with($absolute, $root)) {
            return substr($absolute, strlen($root));
        }

        return $absolute;
    }

    private static function matches(string $glob, string $path): bool
    {
        $regex = self::globToRegex($glob);

        return preg_match($regex, $path) === 1;
    }

    private static function globToRegex(string $glob): string
    {
        $out = '';
        $len = strlen($glob);
        for ($i = 0; $i < $len; $i++) {
            $c = $glob[$i];
            if ($c === '*') {
                if ($i + 1 < $len && $glob[$i + 1] === '*') {
                    $out .= '.*';
                    $i++;
                    if ($i + 1 < $len && $glob[$i + 1] === '/') {
                        $i++;
                    }
                    continue;
                }
                $out .= '[^/]*';
                continue;
            }
            if ($c === '?') {
                $out .= '[^/]';
                continue;
            }
            $out .= preg_quote($c, '#');
        }

        return '#^'.$out.'$#';
    }
}
