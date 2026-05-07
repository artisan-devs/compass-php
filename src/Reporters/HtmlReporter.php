<?php

declare(strict_types=1);

namespace Sidetours\Compass\Reporters;

use Sidetours\Compass\Engine\Result;
use Sidetours\Compass\Engine\Violation;
use Sidetours\Compass\Rules\BuiltInRules;
use Sidetours\Compass\Rules\Rule;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

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
            $body = $this->renderViolationsCard($byRule, $byFile, $rulesByName);
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
     * Combined "By rule" / "By file" card with tabs in the header. The two views were
     * previously two separate cards stacked vertically; the tabbed form removes the
     * scroll cost of switching between them and preserves separate filter inputs per view.
     *
     * @param array<string, list<Violation>> $byRule
     * @param array<string, list<Violation>> $byFile
     * @param array<string, Rule>            $rulesByName
     */
    private function renderViolationsCard(array $byRule, array $byFile, array $rulesByName): string
    {
        $categoryByRule = $this->buildCategoryByRule($rulesByName);
        $rules = $this->renderRulesPanel($byRule, $rulesByName, $categoryByRule);
        $files = $this->renderFilesPanel($byFile, $categoryByRule);

        if ($rules === null && $files === null) {
            return '';
        }

        $categoryFilter = $this->renderCategoryFilter($rulesByName, $categoryByRule);

        return sprintf(
            '<section class="card">'
            .'<header class="card__header card__header--tabs">'
            .'<div class="tabs" role="tablist" aria-label="Violations grouping">'
            .'<button class="tab" role="tab" id="tab-rules" data-tab-trigger="rules" aria-controls="panel-rules" aria-selected="true" type="button">By rule <span class="tab__count">%d</span></button>'
            .'<button class="tab" role="tab" id="tab-files" data-tab-trigger="files" aria-controls="panel-files" aria-selected="false" tabindex="-1" type="button">By file <span class="tab__count">%d</span></button>'
            .'</div>'
            .'</header>'
            .'<div class="card__body">'
            .'%s'
            .'<section class="tab-panel" id="panel-rules" role="tabpanel" aria-labelledby="tab-rules" data-tab-panel="rules">%s</section>'
            .'<section class="tab-panel" id="panel-files" role="tabpanel" aria-labelledby="tab-files" data-tab-panel="files" hidden>%s</section>'
            .'</div>'
            .'</section>',
            $rules['count'] ?? 0,
            $files['count'] ?? 0,
            $categoryFilter,
            $rules['html'] ?? $this->renderTabPlaceholder('No rules configured.'),
            $files['html'] ?? $this->renderTabPlaceholder('No files affected.'),
        );
    }

    /**
     * Build a map of rule short-name => category short-name. Built-in rules look up
     * their category in {@see BuiltInRules::CATEGORIES}; everything else (custom
     * host-project rules) lands in a synthetic "custom" bucket so the chip filter
     * can still hide/show it.
     *
     * @param array<string, Rule> $rulesByName
     * @return array<string, string>
     */
    private function buildCategoryByRule(array $rulesByName): array
    {
        $reverse = [];
        foreach (BuiltInRules::CATEGORIES as $category => $names) {
            foreach ($names as $name) {
                $reverse[$name] = $category;
            }
        }

        $out = [];
        foreach (array_keys($rulesByName) as $name) {
            $out[$name] = $reverse[$name] ?? self::CUSTOM_CATEGORY;
        }

        return $out;
    }

    private const CUSTOM_CATEGORY = 'custom';

    /**
     * Render the multi-check category chip row that sits above the tab panels.
     * Only categories that have at least one configured rule appear.
     *
     * @param array<string, Rule>  $rulesByName
     * @param array<string, string> $categoryByRule
     */
    private function renderCategoryFilter(array $rulesByName, array $categoryByRule): string
    {
        // Preserve CATEGORIES insertion order (type-safety, modern-php, code-hygiene, architecture)
        // and append "custom" if any custom rule is configured.
        $present = [];
        foreach (BuiltInRules::CATEGORIES as $category => $_names) {
            $present[$category] = false;
        }
        $present[self::CUSTOM_CATEGORY] = false;
        foreach ($categoryByRule as $cat) {
            $present[$cat] = true;
        }

        $chips = '';
        foreach ($present as $category => $hasRules) {
            if (! $hasRules) {
                continue;
            }
            $chips .= sprintf(
                '<label class="category-chip" data-category-chip="%s">'
                .'<input type="checkbox" data-category-filter="%s" checked>'
                .'<span class="category-chip__label">%s</span>'
                .'</label>',
                $this->escape($category),
                $this->escape($category),
                $this->escape($category),
            );
        }

        if ($chips === '') {
            return '';
        }

        return '<div class="category-filter" role="group" aria-label="Filter by rule category">'
            .'<span class="category-filter__label">Categories</span>'
            .$chips
            .'</div>';
    }

    /**
     * Build the inner contents of the "By rule" tab: a search filter plus a table.
     * Returns null when there's nothing to show, otherwise an array with a `count`
     * (entries shown) and the rendered HTML for the panel body.
     *
     * @param array<string, list<Violation>> $byRule
     * @param array<string, Rule>            $rulesByName
     * @param array<string, string>          $categoryByRule
     * @return array{count:int,html:string}|null
     */
    private function renderRulesPanel(array $byRule, array $rulesByName, array $categoryByRule): ?array
    {
        $allRules = array_unique(array_merge(array_keys($byRule), array_keys($rulesByName)));
        if ($allRules === []) {
            return null;
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
            $category = $categoryByRule[$name] ?? self::CUSTOM_CATEGORY;
            $rows .= sprintf(
                '<tr data-category="%s" data-violations="%d"><td class="data-table__name"><a href="rules/%s.html">%s</a></td>'
                .'<td class="data-table__desc">%s</td>'
                .'<td class="num"><span class="%s">%d</span></td></tr>',
                $this->escape($category),
                $count,
                $this->escape($this->ruleSlug($name)),
                $this->escape($name),
                $this->escape($description),
                $pillClass,
                $count,
            );
        }

        $html = '<div class="tab-panel__filter">'
            .'<input type="search" class="filter" data-filter-target="#rules-table" placeholder="Filter rules…">'
            .'<button type="button" class="filter-toggle" data-hide-empty aria-pressed="false" title="Hide rules with zero violations">Hide empty</button>'
            .'</div>'
            .'<table id="rules-table" class="data-table">'
            .'<thead><tr><th>Rule</th><th>Description</th><th class="num">Violations</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>';

        return ['count' => count($allRules), 'html' => $html];
    }

    /**
     * Build the inner contents of the "By file" tab: a search filter plus a table.
     * Each row carries `data-categories` (a space-separated list) for category-chip
     * filtering — a file may span multiple rule categories.
     *
     * @param array<string, list<Violation>> $byFile
     * @param array<string, string>          $categoryByRule
     * @return array{count:int,html:string}|null
     */
    private function renderFilesPanel(array $byFile, array $categoryByRule): ?array
    {
        if ($byFile === []) {
            return null;
        }

        $entries = [];
        foreach ($byFile as $path => $violations) {
            $rules = [];
            $categories = [];
            foreach ($violations as $v) {
                $rules[$v->rule] = true;
                $cat = $categoryByRule[$v->rule] ?? self::CUSTOM_CATEGORY;
                $categories[$cat] = true;
            }
            $entries[] = [
                'path' => $path,
                'count' => count($violations),
                'rules' => array_keys($rules),
                'categories' => array_keys($categories),
            ];
        }
        usort($entries, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['path'], $b['path']));

        $rows = '';
        foreach ($entries as $entry) {
            $rules = $entry['rules'];
            sort($rules);
            $categories = $entry['categories'];
            sort($categories);
            $rulesText = $this->escape(implode(', ', $rules));
            $rows .= sprintf(
                '<tr data-categories="%s"><td class="data-table__name"><a href="files/%s.html">%s</a></td>'
                .'<td class="data-table__desc">%s</td>'
                .'<td class="num"><span class="pill pill--danger">%d</span></td></tr>',
                $this->escape(implode(' ', $categories)),
                $this->escape($this->fileSlug($entry['path'])),
                $this->escape($entry['path']),
                $rulesText,
                $entry['count'],
            );
        }

        $html = '<div class="tab-panel__filter">'
            .'<input type="search" class="filter" data-filter-target="#files-table" placeholder="Filter files…">'
            .'</div>'
            .'<table id="files-table" class="data-table">'
            .'<thead><tr><th>File</th><th>Rules</th><th class="num">Violations</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>';

        return ['count' => count($entries), 'html' => $html];
    }

    private function renderTabPlaceholder(string $message): string
    {
        return '<p class="tab-panel__placeholder">'.$this->escape($message).'</p>';
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

        $fixRecipe = $this->renderFixPromptCard($rule);

        return $this->layout(
            title: $name.' · Compass',
            base: '../',
            content: $breadcrumb.$header.$meta.$stats.$body.$fixRecipe,
        );
    }

    /**
     * Render the rule's `fixPrompt()` markdown as a "Fix recipe" card on the rule page,
     * with frontmatter risk / auto-fixable / verification badges surfaced separately.
     * Returns an empty string when the rule is unknown (custom rule not registered)
     * or when the prompt file is missing / unreadable.
     */
    private function renderFixPromptCard(?Rule $rule): string
    {
        if ($rule === null) {
            return '';
        }
        try {
            $prompt = $rule->fixPrompt();
        } catch (\Throwable) {
            return '';
        }
        if ($prompt === '') {
            return '';
        }

        [$frontmatter, $body] = $this->splitFrontmatter($prompt);
        if ($body === '') {
            return '';
        }

        return sprintf(
            '<section class="card">'
            .'<header class="card__header">'
            .'<h3 class="card__title">Fix recipe</h3>'
            .'<button type="button" class="copy-btn" data-copy-prompt="%s" data-copy-label="Copy prompt">Copy prompt</button>'
            .'</header>'
            .'<div class="card__body card__body--padded">%s<pre class="prompt-body">%s</pre></div>'
            .'</section>',
            $this->escape($prompt),
            $this->renderPromptBadges($frontmatter),
            $this->escape($body),
        );
    }

    /**
     * Split a YAML-frontmatter markdown document into [frontmatter, body].
     * If the document doesn't start with a `---` fence or the fence is unbalanced,
     * returns [[], $prompt] so the body is still rendered.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function splitFrontmatter(string $prompt): array
    {
        if (! str_starts_with($prompt, "---\n")) {
            return [[], $prompt];
        }
        $end = strpos($prompt, "\n---\n", 4);
        if ($end === false) {
            return [[], $prompt];
        }
        $yaml = substr($prompt, 4, $end - 4);
        $body = substr($prompt, $end + 5);
        try {
            $parsed = Yaml::parse($yaml);
        } catch (\Throwable) {
            $parsed = [];
        }
        if (! is_array($parsed)) {
            $parsed = [];
        }

        return [$parsed, ltrim($body, "\n")];
    }

    /**
     * @param array<string, mixed> $frontmatter
     */
    private function renderPromptBadges(array $frontmatter): string
    {
        $badges = [];
        if (isset($frontmatter['risk']) && is_string($frontmatter['risk'])) {
            $risk = $frontmatter['risk'];
            $badges[] = sprintf(
                '<span class="badge badge--risk-%s">Risk: %s</span>',
                $this->escape($risk),
                $this->escape($risk),
            );
        }
        if (! empty($frontmatter['auto_fixable'])) {
            $badges[] = '<span class="badge badge--ok">Auto-fixable</span>';
        }
        if (isset($frontmatter['verification']) && is_string($frontmatter['verification'])) {
            $badges[] = sprintf(
                '<span class="badge">Verify: <code>%s</code></span>',
                $this->escape($frontmatter['verification']),
            );
        }

        return $badges === [] ? '' : '<div class="prompt-meta">'.implode('', $badges).'</div>';
    }

    /**
     * @param list<Violation> $violations
     */
    private function renderFilePage(string $relativePath, string $source, array $violations): string
    {
        $normalised = $source === '' ? '' : str_replace(["\r\n", "\r"], "\n", $source);
        $lineCount = $normalised === '' ? 1 : substr_count($normalised, "\n") + 1;
        $highlighted = $this->highlightLines($normalised, $lineCount);

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
        for ($i = 0; $i < $lineCount; $i++) {
            $lineNo = $i + 1;
            $hasViolation = isset($byLine[$lineNo]);
            $rowClass = $hasViolation ? 'line line--violation' : 'line';
            $code = $highlighted[$i] === '' ? "\u{00a0}" : $highlighted[$i];
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
            ['label' => 'Lines', 'value' => $lineCount],
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
    /**
     * Tokenise PHP source via `token_get_all` and emit one HTML-encoded line
     * per array entry, with each token wrapped in a `tok-*` span the stylesheet
     * can colour. Indices are 0-based; element $i corresponds to source line $i+1.
     *
     * @return list<string>
     */
    private function highlightLines(string $source, int $lineCount): array
    {
        $lines = array_fill(0, max($lineCount, 1), '');

        if ($source === '') {
            return $lines;
        }

        // token_get_all needs `<?php` to tokenise as PHP. If the source already opens
        // with one, we tokenise as-is. Otherwise we prepend a synthetic `<?php\n` and
        // discard tokens emitted from that prefix line via $skipFromLine.
        $needsPrefix = ! preg_match('/^\s*<\?php/', $source);
        $input = $needsPrefix ? "<?php\n".$source : $source;
        $skipFromLine = $needsPrefix ? 2 : 1;

        try {
            $tokens = @token_get_all($input);
        } catch (\Throwable) {
            $tokens = [];
        }

        if ($tokens === []) {
            foreach (explode("\n", $source) as $i => $text) {
                $lines[$i] = $this->escape($text);
            }

            return $lines;
        }

        $cursor = 1; // Line we're currently appending to (1-based, in tokeniser input space).
        foreach ($tokens as $token) {
            if (is_array($token)) {
                [$type, $text] = $token;
                $class = $this->phpTokenClass($type);
            } else {
                $text = $token;
                $class = 'tok-punct';
            }

            $parts = explode("\n", $text);
            $partsCount = count($parts);
            foreach ($parts as $idx => $part) {
                $sourceLine = $cursor - $skipFromLine + 1;
                if ($cursor >= $skipFromLine && $sourceLine <= count($lines) && $part !== '') {
                    $escaped = $this->escape($part);
                    $lines[$sourceLine - 1] .= $class === ''
                        ? $escaped
                        : '<span class="'.$class.'">'.$escaped.'</span>';
                }
                if ($idx < $partsCount - 1) {
                    $cursor++;
                }
            }
        }

        return $lines;
    }

    /**
     * Map a `token_get_all` constant to a `tok-*` CSS class. Returns '' for
     * whitespace (no wrapping span needed).
     */
    private function phpTokenClass(int $type): string
    {
        return match (true) {
            $type === T_WHITESPACE => '',
            $type === T_OPEN_TAG, $type === T_CLOSE_TAG, $type === T_OPEN_TAG_WITH_ECHO => 'tok-tag',
            $type === T_COMMENT, $type === T_DOC_COMMENT => 'tok-comment',
            $type === T_VARIABLE => 'tok-var',
            $type === T_LNUMBER, $type === T_DNUMBER => 'tok-number',
            $type === T_CONSTANT_ENCAPSED_STRING,
            $type === T_ENCAPSED_AND_WHITESPACE,
            $type === T_INLINE_HTML => 'tok-string',
            $type === T_STRING_VARNAME, $type === T_NUM_STRING => 'tok-string',
            $type === T_STRING => 'tok-name',
            $type === T_NAME_FULLY_QUALIFIED, $type === T_NAME_QUALIFIED, $type === T_NAME_RELATIVE => 'tok-name',
            default => $this->isPhpKeywordToken($type) ? 'tok-keyword' : 'tok-punct',
        };
    }

    /**
     * Heuristic: every T_* token whose `token_name()` doesn't end in a punctuation-y
     * suffix is a keyword/operator we want highlighted. Cheaper than enumerating ~150 constants.
     */
    private function isPhpKeywordToken(int $type): bool
    {
        $name = token_name($type);

        return $name !== 'UNKNOWN' && str_starts_with($name, 'T_');
    }

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
        $highlightOptions = '';
        foreach (self::HIGHLIGHT_THEMES as $value => $label) {
            $highlightOptions .= sprintf(
                '<option value="%s">%s</option>',
                $this->escape($value),
                $this->escape($label),
            );
        }

        return '<header class="topbar"><div class="topbar__inner">'
            .'<a class="brand" href="'.$this->escape($base).'index.html"><span class="brand__mark"></span>Compass</a>'
            .'<div class="nav"></div>'
            .'<label class="control control--select" aria-label="Code theme">'
            .'<svg class="control__icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 4 2 8 5 12"/><polyline points="11 4 14 8 11 12"/><line x1="9.5" y1="3" x2="6.5" y2="13"/></svg>'
            .'<select data-highlight-theme>'.$highlightOptions.'</select>'
            .'</label>'
            .'<button class="icon-btn" data-theme-toggle aria-label="Toggle theme">'
            .'<svg class="theme-icon-light" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="3.2"/><path d="M8 1.5v1.6M8 12.9v1.6M1.5 8h1.6M12.9 8h1.6M3.2 3.2l1.1 1.1M11.7 11.7l1.1 1.1M3.2 12.8l1.1-1.1M11.7 4.3l1.1-1.1"/></svg>'
            .'<svg class="theme-icon-dark" viewBox="0 0 16 16" fill="currentColor"><path d="M11.8 9.7a5 5 0 0 1-6.3-6.3 5.5 5.5 0 1 0 6.3 6.3z"/></svg>'
            .'</button>'
            .'</div></header>';
    }

    /**
     * Highlight-theme key (CSS class suffix) => display label shown in the topbar dropdown.
     * The "default" entry intentionally maps to no class — it leaves the theme-aware
     * `--tok-*` variables from `:root` / `:root.theme-dark` in effect.
     */
    private const HIGHLIGHT_THEMES = [
        'default' => 'Default',
        'github-light' => 'GitHub Light',
        'monokai' => 'Monokai',
        'monokai-dimmed' => 'Monokai Dimmed',
        'dracula' => 'Dracula',
        'solarized-light' => 'Solarized Light',
        'nord' => 'Nord',
    ];

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
