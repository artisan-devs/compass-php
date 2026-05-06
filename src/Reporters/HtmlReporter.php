<?php

declare(strict_types=1);

namespace Sidetours\Compass\Reporters;

use Sidetours\Compass\Engine\Result;
use Sidetours\Compass\Engine\Violation;
use Sidetours\Compass\Rules\Rule;
use Symfony\Component\Console\Output\OutputInterface;

final class HtmlReporter implements Reporter
{
    /**
     * @param list<Rule> $rules Registered rules; used to enrich the report with each rule's description.
     */
    public function __construct(
        private readonly string $outputDir,
        private readonly array $rules = [],
    ) {
    }

    public function report(Result $result, OutputInterface $output, string $projectRoot): void
    {
        $this->ensureDirectory($this->outputDir);
        $this->ensureDirectory($this->outputDir.'/assets');
        $this->ensureDirectory($this->outputDir.'/rules');
        $this->ensureDirectory($this->outputDir.'/files');

        file_put_contents($this->outputDir.'/assets/styles.css', $this->loadAsset('styles.css'));
        file_put_contents($this->outputDir.'/assets/app.js', $this->loadAsset('app.js'));

        $rulesByName = [];
        foreach ($this->rules as $rule) {
            $rulesByName[$rule->name()] = $rule;
        }

        /** @var array<string, list<Violation>> $byRule */
        $byRule = [];
        /** @var array<string, list<Violation>> $byFile */
        $byFile = [];
        /** @var array<string, string> $absoluteByRelative */
        $absoluteByRelative = [];

        foreach ($result->violations as $violation) {
            $byRule[$violation->rule][] = $violation;
            $relative = $this->relative($violation->file, $projectRoot);
            $byFile[$relative][] = $violation;
            $absoluteByRelative[$relative] = $violation->file;
        }

        ksort($byRule);
        ksort($byFile);

        file_put_contents(
            $this->outputDir.'/index.html',
            $this->renderIndex($result, $byRule, $byFile, $rulesByName),
        );

        $allRuleNames = array_unique(array_merge(array_keys($byRule), array_keys($rulesByName)));
        sort($allRuleNames);
        foreach ($allRuleNames as $ruleName) {
            $violations = $byRule[$ruleName] ?? [];
            $rule = $rulesByName[$ruleName] ?? null;
            file_put_contents(
                $this->outputDir.'/rules/'.$this->ruleSlug($ruleName).'.html',
                $this->renderRulePage($ruleName, $rule, $violations, $projectRoot),
            );
        }

        foreach ($byFile as $relativePath => $violations) {
            $absolute = $absoluteByRelative[$relativePath] ?? null;
            $source = $absolute !== null ? @file_get_contents($absolute) : false;
            if ($source === false) {
                $source = '';
            }
            file_put_contents(
                $this->outputDir.'/files/'.$this->fileSlug($relativePath).'.html',
                $this->renderFilePage($relativePath, $source, $violations),
            );
        }

        $output->writeln(sprintf('<info>Compass HTML report written to:</info> %s/index.html', $this->outputDir));
    }

    private function renderIndex(Result $result, array $byRule, array $byFile, array $rulesByName): string
    {
        $totalViolations = count($result->violations);
        $filesAffected = count($byFile);
        $rulesTriggered = count($byRule);
        $errors = count($result->errors);
        $ignored = count($result->ignored);

        $stats = $this->renderStats([
            ['label' => 'Violations', 'value' => $totalViolations, 'tone' => $totalViolations > 0 ? 'danger' : 'ok', 'hint' => $ignored > 0 ? sprintf('+%d suppressed', $ignored) : null],
            ['label' => 'Files affected', 'value' => $filesAffected, 'hint' => sprintf('of %d scanned', $result->filesScanned)],
            ['label' => 'Rules triggered', 'value' => $rulesTriggered, 'hint' => sprintf('of %d configured', count($rulesByName) ?: $rulesTriggered)],
            ['label' => 'Parse errors', 'value' => $errors, 'tone' => $errors > 0 ? 'danger' : null],
        ]);

        $errorsBlock = $this->renderErrors($result->errors);

        if ($totalViolations === 0 && $errors === 0) {
            $body = $this->renderEmptyState($result);
        } else {
            $body = $this->renderRulesCard($byRule, $rulesByName)
                .$this->renderFilesCard($byFile);
        }

        $generatedAt = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $header = sprintf(
            '<header class="page-header">'
            .'<p class="page-header__eyebrow">Generated %s</p>'
            .'<h2>Architecture &amp; style report</h2>'
            .'<p class="page-header__subtitle">Scanned %d file%s · %d rule%s configured</p>'
            .'</header>',
            $this->escape($generatedAt),
            $result->filesScanned,
            $result->filesScanned === 1 ? '' : 's',
            count($rulesByName),
            count($rulesByName) === 1 ? '' : 's',
        );

        return $this->layout(
            title: 'Compass Report',
            base: '',
            content: $header.$stats.$errorsBlock.$body,
        );
    }

    /**
     * @param array<string, list<Violation>> $byRule
     * @param array<string, Rule>            $rulesByName
     */
    private function renderRulesCard(array $byRule, array $rulesByName): string
    {
        $allRules = array_unique(array_merge(array_keys($byRule), array_keys($rulesByName)));
        if ($allRules === []) {
            return '';
        }

        usort($allRules, static function (string $a, string $b) use ($byRule): int {
            $cmp = count($byRule[$b] ?? []) <=> count($byRule[$a] ?? []);

            return $cmp !== 0 ? $cmp : strcmp($a, $b);
        });

        $rows = '';
        foreach ($allRules as $name) {
            $count = count($byRule[$name] ?? []);
            $rule = $rulesByName[$name] ?? null;
            $description = $rule?->shortDescription() ?? '';
            $pillClass = $count > 0 ? 'pill pill--danger' : 'pill pill--ok';
            $rows .= sprintf(
                '<tr><td class="data-table__name"><a href="rules/%s.html">%s</a></td>'
                .'<td class="data-table__desc">%s</td>'
                .'<td class="num"><span class="%s">%d</span></td></tr>',
                $this->escape($this->ruleSlug($name)),
                $this->escape($name),
                $this->escape($description),
                $pillClass,
                $count,
            );
        }

        return sprintf(
            '<section class="card">'
            .'<header class="card__header">'
            .'<h3 class="card__title">By rule <span class="card__count">%d</span></h3>'
            .'<input type="search" class="filter" data-filter-target="#rules-table" placeholder="Filter rules…">'
            .'</header>'
            .'<div class="card__body">'
            .'<table id="rules-table" class="data-table">'
            .'<thead><tr><th>Rule</th><th>Description</th><th class="num">Violations</th></tr></thead>'
            .'<tbody>%s</tbody>'
            .'</table>'
            .'</div>'
            .'</section>',
            count($allRules),
            $rows,
        );
    }

    /**
     * @param array<string, list<Violation>> $byFile
     */
    private function renderFilesCard(array $byFile): string
    {
        if ($byFile === []) {
            return '';
        }

        $entries = [];
        foreach ($byFile as $path => $violations) {
            $rules = [];
            foreach ($violations as $v) {
                $rules[$v->rule] = true;
            }
            $entries[] = ['path' => $path, 'count' => count($violations), 'rules' => array_keys($rules)];
        }
        usort($entries, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['path'], $b['path']));

        $rows = '';
        foreach ($entries as $entry) {
            $rules = $entry['rules'];
            sort($rules);
            $rulesText = $this->escape(implode(', ', $rules));
            $rows .= sprintf(
                '<tr><td class="data-table__name"><a href="files/%s.html">%s</a></td>'
                .'<td class="data-table__desc">%s</td>'
                .'<td class="num"><span class="pill pill--danger">%d</span></td></tr>',
                $this->escape($this->fileSlug($entry['path'])),
                $this->escape($entry['path']),
                $rulesText,
                $entry['count'],
            );
        }

        return sprintf(
            '<section class="card">'
            .'<header class="card__header">'
            .'<h3 class="card__title">By file <span class="card__count">%d</span></h3>'
            .'<input type="search" class="filter" data-filter-target="#files-table" placeholder="Filter files…">'
            .'</header>'
            .'<div class="card__body">'
            .'<table id="files-table" class="data-table">'
            .'<thead><tr><th>File</th><th>Rules</th><th class="num">Violations</th></tr></thead>'
            .'<tbody>%s</tbody>'
            .'</table>'
            .'</div>'
            .'</section>',
            count($entries),
            $rows,
        );
    }

    /**
     * @param list<Violation> $violations
     */
    private function renderRulePage(string $name, ?Rule $rule, array $violations, string $projectRoot): string
    {
        $description = $rule?->shortDescription() ?? '—';

        $byFile = [];
        foreach ($violations as $v) {
            $relative = $this->relative($v->file, $projectRoot);
            $byFile[$relative][] = $v;
        }
        ksort($byFile);

        $stats = $this->renderStats([
            ['label' => 'Violations', 'value' => count($violations), 'tone' => count($violations) > 0 ? 'danger' : 'ok'],
            ['label' => 'Files affected', 'value' => count($byFile)],
        ]);

        if ($violations === []) {
            $body = '<section class="empty">'
                .'<div class="empty__icon">✓</div>'
                .'<h3 class="empty__title">No violations for this rule</h3>'
                .'<p class="empty__subtitle">Great — every checked file passed <code>'.$this->escape($name).'</code>.</p>'
                .'</section>';
        } else {
            $groups = '';
            foreach ($byFile as $path => $items) {
                $itemsHtml = '';
                foreach ($items as $v) {
                    $itemsHtml .= sprintf(
                        '<li><span class="line-no"><a href="../files/%s.html#L%d">line %d</a></span><span class="msg">%s</span></li>',
                        $this->escape($this->fileSlug($path)),
                        $v->line,
                        $v->line,
                        $this->escape($v->message),
                    );
                }
                $groups .= sprintf(
                    '<div class="violation-group">'
                    .'<div class="violation-group__file"><a href="../files/%s.html">%s</a><span class="card__count">%d</span></div>'
                    .'<ul class="violation-list">%s</ul>'
                    .'</div>',
                    $this->escape($this->fileSlug($path)),
                    $this->escape($path),
                    count($items),
                    $itemsHtml,
                );
            }
            $body = sprintf(
                '<section class="card">'
                .'<header class="card__header"><h3 class="card__title">Violations <span class="card__count">%d</span></h3></header>'
                .'<div class="card__body">%s</div>'
                .'</section>',
                count($violations),
                $groups,
            );
        }

        $meta = sprintf(
            '<div class="rule-meta">'
            .'<div><p class="rule-meta__label">Rule</p><p class="rule-meta__value"><span class="rule-meta__name">%s</span></p></div>'
            .'<div><p class="rule-meta__label">Description</p><p class="rule-meta__value">%s</p></div>'
            .'</div>',
            $this->escape($name),
            $this->escape($description),
        );

        $header = sprintf(
            '<header class="page-header">'
            .'<p class="page-header__eyebrow">Rule</p>'
            .'<h2>%s</h2>'
            .'</header>',
            $this->escape($name),
        );

        $breadcrumb = $this->renderBreadcrumb([
            ['Overview', '../index.html'],
            ['Rules', null],
            [$name, null],
        ]);

        return $this->layout(
            title: $name.' · Compass',
            base: '../',
            content: $breadcrumb.$header.$meta.$stats.$body,
        );
    }

    /**
     * @param list<Violation> $violations
     */
    private function renderFilePage(string $relativePath, string $source, array $violations): string
    {
        $lines = $source === '' ? [''] : explode("\n", str_replace(["\r\n", "\r"], "\n", $source));

        /** @var array<int, list<Violation>> $byLine */
        $byLine = [];
        foreach ($violations as $v) {
            $byLine[$v->line][] = $v;
        }

        $rules = [];
        foreach ($violations as $v) {
            $rules[$v->rule] = true;
        }

        $rows = '';
        foreach ($lines as $i => $text) {
            $lineNo = $i + 1;
            $hasViolation = isset($byLine[$lineNo]);
            $rowClass = $hasViolation ? 'line line--violation' : 'line';
            $code = $text === '' ? "\u{00a0}" : $this->escape($text);
            $rows .= sprintf(
                '<tr class="%s" id="L%d">'
                .'<td class="ln"><a href="#L%d">%d</a></td>'
                .'<td class="code">%s</td>'
                .'</tr>',
                $rowClass,
                $lineNo,
                $lineNo,
                $lineNo,
                $code,
            );
            if ($hasViolation) {
                $items = '';
                foreach ($byLine[$lineNo] as $v) {
                    $items .= sprintf(
                        '<li><a class="rule-tag" href="../rules/%s.html">%s</a><span class="msg">%s</span></li>',
                        $this->escape($this->ruleSlug($v->rule)),
                        $this->escape($v->rule),
                        $this->escape($v->message),
                    );
                }
                $rows .= sprintf(
                    '<tr class="annotation"><td class="ln"></td><td class="code"><ul class="annotation-list">%s</ul></td></tr>',
                    $items,
                );
            }
        }

        $stats = $this->renderStats([
            ['label' => 'Violations', 'value' => count($violations), 'tone' => 'danger'],
            ['label' => 'Rules triggered', 'value' => count($rules)],
            ['label' => 'Lines', 'value' => count($lines)],
        ]);

        $sourceCard = sprintf(
            '<section class="card"><header class="card__header"><h3 class="card__title">Source</h3></header>'
            .'<div class="source-frame"><table class="source"><tbody>%s</tbody></table></div></section>',
            $rows,
        );

        $header = sprintf(
            '<header class="page-header">'
            .'<p class="page-header__eyebrow">File</p>'
            .'<h2 class="page-header__path">%s</h2>'
            .'</header>',
            $this->escape($relativePath),
        );

        $breadcrumb = $this->renderBreadcrumb([
            ['Overview', '../index.html'],
            ['Files', null],
            [basename($relativePath), null],
        ]);

        return $this->layout(
            title: $relativePath.' · Compass',
            base: '../',
            content: $breadcrumb.$header.$stats.$sourceCard,
        );
    }

    /**
     * @param list<array{label:string,value:int|string,tone?:?string,hint?:?string}> $stats
     */
    private function renderStats(array $stats): string
    {
        $cards = '';
        foreach ($stats as $stat) {
            $tone = $stat['tone'] ?? null;
            $hint = $stat['hint'] ?? null;
            $cards .= sprintf(
                '<article class="stat%s"><span class="stat__label">%s</span><span class="stat__value">%s</span>%s</article>',
                $tone !== null ? ' stat--'.$tone : '',
                $this->escape($stat['label']),
                $this->escape((string) $stat['value']),
                $hint !== null ? '<span class="stat__hint">'.$this->escape($hint).'</span>' : '',
            );
        }

        return '<section class="stats">'.$cards.'</section>';
    }

    /**
     * @param list<string> $errors
     */
    private function renderErrors(array $errors): string
    {
        if ($errors === []) {
            return '';
        }
        $items = '';
        foreach ($errors as $error) {
            $items .= '<li>'.$this->escape($error).'</li>';
        }

        return sprintf(
            '<section class="errors"><h3 class="errors__title">Parser errors</h3><ul class="errors__list">%s</ul></section>',
            $items,
        );
    }

    private function renderEmptyState(Result $result): string
    {
        return sprintf(
            '<section class="empty">'
            .'<div class="empty__icon">✓</div>'
            .'<h3 class="empty__title">No violations</h3>'
            .'<p class="empty__subtitle">Compass scanned %d file%s and found nothing to flag.</p>'
            .'</section>',
            $result->filesScanned,
            $result->filesScanned === 1 ? '' : 's',
        );
    }

    /**
     * @param list<array{0:string,1:?string}> $items
     */
    private function renderBreadcrumb(array $items): string
    {
        $parts = [];
        foreach ($items as $i => [$label, $href]) {
            $isLast = $i === array_key_last($items);
            if ($href !== null && ! $isLast) {
                $parts[] = sprintf('<a href="%s">%s</a>', $this->escape($href), $this->escape($label));
            } else {
                $parts[] = sprintf('<span class="breadcrumb__current">%s</span>', $this->escape($label));
            }
        }

        return '<nav class="breadcrumb">'.implode('<span class="breadcrumb__sep">›</span>', $parts).'</nav>';
    }

    private function layout(string $title, string $base, string $content): string
    {
        return '<!DOCTYPE html>'
            .'<html lang="en">'
            .'<head>'
            .'<meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>'.$this->escape($title).'</title>'
            .'<link rel="stylesheet" href="'.$base.'assets/styles.css">'
            .'</head>'
            .'<body>'
            .$this->renderTopbar($base)
            .'<main class="container">'.$content.'</main>'
            .'<footer class="footer">Generated by <code>compass</code></footer>'
            .'<script src="'.$base.'assets/app.js"></script>'
            .'</body>'
            .'</html>';
    }

    private function renderTopbar(string $base): string
    {
        return '<header class="topbar"><div class="topbar__inner">'
            .'<a class="brand" href="'.$this->escape($base).'index.html"><span class="brand__mark"></span>Compass</a>'
            .'<div class="nav"></div>'
            .'<button class="icon-btn" data-theme-toggle aria-label="Toggle theme">'
            .'<svg class="theme-icon-light" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="3.2"/><path d="M8 1.5v1.6M8 12.9v1.6M1.5 8h1.6M12.9 8h1.6M3.2 3.2l1.1 1.1M11.7 11.7l1.1 1.1M3.2 12.8l1.1-1.1M11.7 4.3l1.1-1.1"/></svg>'
            .'<svg class="theme-icon-dark" viewBox="0 0 16 16" fill="currentColor"><path d="M11.8 9.7a5 5 0 0 1-6.3-6.3 5.5 5.5 0 1 0 6.3 6.3z"/></svg>'
            .'</button>'
            .'</div></header>';
    }

    private function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException(sprintf('Could not create HTML report directory: %s', $dir));
        }
    }

    private function loadAsset(string $name): string
    {
        $path = __DIR__.'/html/'.$name;
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('HTML report asset missing: %s', $path));
        }

        return $contents;
    }

    private function relative(string $absolute, string $projectRoot): string
    {
        $root = rtrim($projectRoot, '/').'/';

        return str_starts_with($absolute, $root) ? substr($absolute, strlen($root)) : $absolute;
    }

    private function ruleSlug(string $rule): string
    {
        $slug = preg_replace('/[^a-z0-9_-]+/i', '-', $rule);

        return $slug !== null && $slug !== '' ? trim($slug, '-') : 'rule';
    }

    private function fileSlug(string $relativePath): string
    {
        $slug = str_replace(['\\', '/'], '__', $relativePath);

        return preg_replace('/[^a-zA-Z0-9._-]+/', '_', $slug) ?? $slug;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
