<?php

declare(strict_types=1);

namespace Sidetours\Compass\Engine;

final class FileScanner
{
    /**
     * @param list<string> $paths
     * @param list<string> $exclude
     * @return iterable<string>
     */
    public function scan(array $paths, array $exclude, string $projectRoot): iterable
    {
        foreach ($paths as $path) {
            yield from $this->scanPath($path, $exclude, $projectRoot);
        }
    }

    /**
     * @param list<string> $exclude
     * @return iterable<string>
     */
    private function scanPath(string $path, array $exclude, string $projectRoot): iterable
    {
        if (is_file($path)) {
            if (! $this->isExcluded($path, $exclude, $projectRoot) && str_ends_with($path, '.php')) {
                yield $path;
            }

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $absolute = $file->getPathname();
            if ($this->isExcluded($absolute, $exclude, $projectRoot)) {
                continue;
            }
            yield $absolute;
        }
    }

    /**
     * @param list<string> $exclude
     */
    private function isExcluded(string $absolute, array $exclude, string $projectRoot): bool
    {
        $root = rtrim($projectRoot, '/').'/';
        $relative = str_starts_with($absolute, $root) ? substr($absolute, strlen($root)) : $absolute;

        foreach ($exclude as $pattern) {
            if ($this->matchesGlob($pattern, $relative) || $this->matchesGlob($pattern, $absolute)) {
                return true;
            }
        }

        return false;
    }

    private function matchesGlob(string $pattern, string $path): bool
    {
        if (str_contains($pattern, '*') || str_contains($pattern, '?')) {
            return fnmatch($pattern, $path) || fnmatch($pattern.'/*', $path);
        }

        return $path === $pattern || str_starts_with($path, rtrim($pattern, '/').'/');
    }
}
