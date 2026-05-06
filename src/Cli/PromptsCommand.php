<?php

declare(strict_types=1);

namespace Sidetours\Compass\Cli;

use Sidetours\Compass\Engine\Configuration;
use Sidetours\Compass\Rules\Rule;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'prompts', description: 'Print or export per-rule fix prompts (YAML frontmatter + markdown).')]
final class PromptsCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to configuration file', 'compass.php')
            ->addOption('rule', null, InputOption::VALUE_REQUIRED, 'Limit output to this rule by name')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Write each prompt as <out>/<rule>.md instead of printing to stdout');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configFile = $this->resolveConfigPath((string) $input->getOption('config'));
        $config = Configuration::load($configFile, $this->projectRoot);

        $filter = $input->getOption('rule');
        $rules = $config->rules;
        if (is_string($filter) && $filter !== '') {
            $rules = array_values(array_filter($rules, static fn (Rule $r) => $r->name() === $filter));
            if ($rules === []) {
                $output->writeln(sprintf('<error>No rule registered with name "%s".</error>', $filter));

                return 2;
            }
        }

        $outDir = $input->getOption('out');
        if (is_string($outDir) && $outDir !== '') {
            return $this->writeFiles($rules, $outDir, $output);
        }

        foreach ($rules as $i => $rule) {
            if ($i > 0) {
                $output->writeln('');
            }
            $output->write($rule->fixPrompt());
        }

        return 0;
    }

    /**
     * @param list<Rule> $rules
     */
    private function writeFiles(array $rules, string $outDir, OutputInterface $output): int
    {
        $abs = $this->resolveOutPath($outDir);
        if (! is_dir($abs) && ! @mkdir($abs, 0775, true) && ! is_dir($abs)) {
            $output->writeln(sprintf('<error>Could not create output directory: %s</error>', $abs));

            return 2;
        }

        foreach ($rules as $rule) {
            $path = $abs.'/'.$rule->name().'.md';
            file_put_contents($path, $rule->fixPrompt());
            $output->writeln(sprintf('<info>Wrote</info> %s', $path));
        }

        return 0;
    }

    private function resolveConfigPath(string $path): string
    {
        return $this->resolvePath($path, 'compass.php');
    }

    private function resolveOutPath(string $path): string
    {
        return $this->resolvePath($path, $path);
    }

    private function resolvePath(string $path, string $default): string
    {
        $path = $path === '' ? $default : $path;
        if ($path !== '' && ($path[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1)) {
            return $path;
        }

        return rtrim($this->projectRoot, '/').'/'.$path;
    }
}
