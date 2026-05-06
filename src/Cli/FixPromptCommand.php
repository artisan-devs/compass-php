<?php

declare(strict_types=1);

namespace Sidetours\Compass\Cli;

use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Engine\Violation;
use Sidetours\Compass\Rules\Rule;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'fix-prompt', description: 'Emit per-rule fix prompts plus the violations matching the given filters, ready to pipe to an LLM.')]
final class FixPromptCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to configuration file', 'compass.yaml')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Limit the scan to files matching this path or glob (relative to project root or absolute). Repeat for several. Supports *, **, ?')
            ->addOption('rule', null, InputOption::VALUE_REQUIRED, 'Restrict output to violations of this rule (built-in short name).')
            ->addOption('first', null, InputOption::VALUE_NONE, 'Emit only the first matching violation per rule (useful for testing one fix at a time).')
            ->addOption('no-baseline', null, InputOption::VALUE_NONE, 'Ignore the configured baseline; consider every violation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configFile = $this->resolveConfigPath((string) $input->getOption('config'));
        $config = Configuration::load($configFile, $this->projectRoot);

        $ignoreList = $input->getOption('no-baseline')
            ? new IgnoreList($config->ignore, [], $config->projectRoot)
            : IgnoreList::fromConfiguration($config);

        /** @var list<string> $filters */
        $filters = (array) $input->getOption('filter');
        $result = (new Runner($config, $ignoreList, filters: $filters))->run();

        $ruleFilter = $input->getOption('rule');
        $onlyFirst = (bool) $input->getOption('first');

        /** @var array<string, list<Violation>> $byRule */
        $byRule = [];
        foreach ($result->violations as $violation) {
            if (is_string($ruleFilter) && $violation->rule !== $ruleFilter) {
                continue;
            }
            $byRule[$violation->rule][] = $violation;
        }

        if ($byRule === []) {
            $output->writeln('<comment># No violations match the given filters — nothing to emit.</comment>');

            return 0;
        }

        /** @var array<string, Rule> $rulesByName */
        $rulesByName = [];
        foreach ($config->rules as $rule) {
            $rulesByName[$rule->name()] = $rule;
        }

        $blocks = [];
        foreach ($byRule as $ruleName => $violations) {
            $rule = $rulesByName[$ruleName] ?? null;
            if ($rule === null) {
                continue;
            }
            if ($onlyFirst) {
                $violations = [$violations[0]];
            }
            $blocks[] = $this->renderBlock($rule, $violations);
        }

        $output->writeln(implode("\n\n---\n\n", $blocks));

        return 0;
    }

    /**
     * @param list<Violation> $violations
     */
    private function renderBlock(Rule $rule, array $violations): string
    {
        $rows = [];
        foreach ($violations as $v) {
            $rows[] = [
                'file' => $this->relative($v->file),
                'line' => $v->line,
                'rule' => $v->rule,
                'message' => $v->message,
            ];
        }
        $json = (string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return sprintf(
            "%s\n\n## Violations to fix\n\n```json\n%s\n```\n",
            rtrim($rule->fixPrompt()),
            $json,
        );
    }

    private function relative(string $absolute): string
    {
        $root = rtrim($this->projectRoot, '/').'/';

        return str_starts_with($absolute, $root) ? substr($absolute, strlen($root)) : $absolute;
    }

    private function resolveConfigPath(string $path): string
    {
        if ($path !== '' && ($path[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1)) {
            return $path;
        }

        return rtrim($this->projectRoot, '/').'/'.($path === '' ? 'compass.yaml' : $path);
    }
}
