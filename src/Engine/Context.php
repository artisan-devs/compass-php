<?php

declare(strict_types=1);

namespace Sidetours\Compass\Engine;

final class Context
{
    /** @var list<string> Inline-ignored rule names for the current file. '*' means all rules. */
    private array $fileLevelIgnores = [];

    /** @var array<int, list<string>> Map of line number => list of ignored rule names ('*' = all). */
    private array $lineLevelIgnores = [];

    public function __construct(
        public readonly string $file,
        public readonly string $source,
    ) {
        $this->parseInlineAnnotations();
    }

    public function isIgnored(string $rule, int $line): bool
    {
        if (in_array('*', $this->fileLevelIgnores, true) || in_array($rule, $this->fileLevelIgnores, true)) {
            return true;
        }

        $candidates = $this->lineLevelIgnores[$line] ?? [];

        return in_array('*', $candidates, true) || in_array($rule, $candidates, true);
    }

    private function parseInlineAnnotations(): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $this->source);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $index => $text) {
            if (preg_match('#@compass-ignore-file(?:\s+([^*\n\r]+))?#', $text, $m) === 1) {
                $this->fileLevelIgnores = array_merge(
                    $this->fileLevelIgnores,
                    self::splitRuleList($m[1] ?? '*'),
                );
                continue;
            }

            if (preg_match('#@compass-ignore-next-line(?:\s+([^*\n\r]+))?#', $text, $m) === 1) {
                $next = $index + 2;
                $this->lineLevelIgnores[$next] = array_merge(
                    $this->lineLevelIgnores[$next] ?? [],
                    self::splitRuleList($m[1] ?? '*'),
                );
                continue;
            }

            if (preg_match('#@compass-ignore(?:\s+([^*\n\r]+))?#', $text, $m) === 1) {
                $lineNumber = $index + 1;
                $this->lineLevelIgnores[$lineNumber] = array_merge(
                    $this->lineLevelIgnores[$lineNumber] ?? [],
                    self::splitRuleList($m[1] ?? '*'),
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function splitRuleList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '*') {
            return ['*'];
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn (string $p) => $p !== '');

        return array_values($parts);
    }
}
