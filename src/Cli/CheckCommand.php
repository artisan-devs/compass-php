<?php

declare(strict_types=1);

namespace Sidetours\Compass\Cli;

use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Engine\IgnoreList;
use Sidetours\Compass\Engine\Runner;
use Sidetours\Compass\Reporters\GithubActionsReporter;
use Sidetours\Compass\Reporters\HtmlReporter;
use Sidetours\Compass\Reporters\JsonReporter;
use Sidetours\Compass\Reporters\Reporter;
use Sidetours\Compass\Reporters\TextReporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check', description: 'Run style/architecture checks against the configured paths.')]
final class CheckCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to configuration file', 'compass.yaml')
            ->addOption('reporter', 'r', InputOption::VALUE_REQUIRED, 'Reporter: text|json|github|html', 'text')
            ->addOption('group-by', 'g', InputOption::VALUE_REQUIRED, 'Group violations: file|rule (text+json) or none (json only)', 'file')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output directory (required by html reporter)')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Limit the scan to files matching this path or glob (relative to project root or absolute). Repeat to match several. Supports *, **, ?')
            ->addOption('no-baseline', null, InputOption::VALUE_NONE, 'Ignore the configured baseline and report every violation');
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
        $runner = new Runner($config, $ignoreList, filters: $filters);

        $result = $runner->run();
        $reporter = $this->reporter(
            name: (string) $input->getOption('reporter'),
            groupBy: (string) $input->getOption('group-by'),
            out: $input->getOption('out'),
            config: $config,
        );
        $reporter->report($result, $output, $this->projectRoot);

        if ($result->errors !== []) {
            return 2;
        }

        return $result->violations === [] ? 0 : 1;
    }

    private function reporter(string $name, string $groupBy, mixed $out, Configuration $config): Reporter
    {
        return match ($name) {
            'json' => new JsonReporter($groupBy),
            'github' => new GithubActionsReporter(),
            'text' => new TextReporter($this->coerceTextGroupBy($groupBy)),
            'html' => new HtmlReporter($this->resolveOutPath($out), $config->rules),
            default => throw new \InvalidArgumentException(sprintf('Unknown reporter "%s". Use text|json|github|html.', $name)),
        };
    }

    private function resolveOutPath(mixed $out): string
    {
        if (! is_string($out) || $out === '') {
            throw new \InvalidArgumentException('The html reporter requires --out=DIR (output directory).');
        }
        if ($out[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $out) === 1) {
            return $out;
        }

        return rtrim($this->projectRoot, '/').'/'.$out;
    }

    private function coerceTextGroupBy(string $groupBy): string
    {
        return $groupBy === 'none' ? TextReporter::GROUP_BY_FILE : $groupBy;
    }

    private function resolveConfigPath(string $path): string
    {
        if ($path !== '' && ($path[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1)) {
            return $path;
        }

        return rtrim($this->projectRoot, '/').'/'.$path;
    }
}
