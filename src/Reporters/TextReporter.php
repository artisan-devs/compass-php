<?php

declare(strict_types=1);

namespace Sidetours\Compass\Reporters;

use Sidetours\Compass\Engine\Result;
use Symfony\Component\Console\Output\OutputInterface;

final class TextReporter implements Reporter
{
    public const GROUP_BY_FILE = 'file';
    public const GROUP_BY_RULE = 'rule';

    public function __construct(private readonly string $groupBy = self::GROUP_BY_FILE)
    {
        if (! in_array($this->groupBy, [self::GROUP_BY_FILE, self::GROUP_BY_RULE], true)) {
            throw new \InvalidArgumentException(sprintf('Unknown group-by mode "%s". Use file|rule.', $this->groupBy));
        }
    }

    public function report(Result $result, OutputInterface $output, string $projectRoot): void
    {
        $root = rtrim($projectRoot, '/').'/';

        if ($result->errors !== []) {
            $output->writeln('<error>Errors:</error>');
            foreach ($result->errors as $error) {
                $output->writeln('  '.$error);
            }
            $output->writeln('');
        }

        if ($result->violations === []) {
            $output->writeln(sprintf(
                '<info>Compass: 0 violations across %d file(s)%s.</info>',
                $result->filesScanned,
                $result->ignored !== [] ? sprintf(' (%d suppressed)', count($result->ignored)) : '',
            ));

            return;
        }

        if ($this->groupBy === self::GROUP_BY_RULE) {
            $this->renderByRule($result->violations, $output, $root);
        } else {
            $this->renderByFile($result->violations, $output, $root);
        }

        $output->writeln(sprintf(
            '<error>Compass: %d violation(s) across %d file(s)%s.</error>',
            count($result->violations),
            $result->filesScanned,
            $result->ignored !== [] ? sprintf(' (%d suppressed)', count($result->ignored)) : '',
        ));
    }

    /**
     * @param list<\Sidetours\Compass\Engine\Violation> $violations
     */
    private function renderByFile(array $violations, OutputInterface $output, string $root): void
    {
        /** @var array<string, array<string, list<\Sidetours\Compass\Engine\Violation>>> $byFile */
        $byFile = [];
        foreach ($violations as $violation) {
            $byFile[$violation->file][$violation->rule][] = $violation;
        }

        foreach ($byFile as $file => $byRule) {
            $relative = str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
            $total = array_sum(array_map('count', $byRule));
            $output->writeln(sprintf('<fg=cyan>%s</> <fg=default>(%d)</>', $relative, $total));

            ksort($byRule);
            foreach ($byRule as $rule => $items) {
                $output->writeln(sprintf(
                    '  <fg=yellow>[%s]</> <fg=default>(%d)</>',
                    $rule,
                    count($items),
                ));
                foreach ($items as $violation) {
                    $output->writeln(sprintf(
                        '    <fg=red>%d</>  %s',
                        $violation->line,
                        $violation->message,
                    ));
                }
            }
            $output->writeln('');
        }
    }

    /**
     * @param list<\Sidetours\Compass\Engine\Violation> $violations
     */
    private function renderByRule(array $violations, OutputInterface $output, string $root): void
    {
        /** @var array<string, array<string, list<\Sidetours\Compass\Engine\Violation>>> $byRule */
        $byRule = [];
        foreach ($violations as $violation) {
            $byRule[$violation->rule][$violation->file][] = $violation;
        }
        ksort($byRule);

        foreach ($byRule as $rule => $byFile) {
            $total = array_sum(array_map('count', $byFile));
            $output->writeln(sprintf('<comment>[%s]</comment> <fg=yellow>%d violation(s)</>', $rule, $total));

            foreach ($byFile as $file => $items) {
                $relative = str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
                $output->writeln(sprintf(
                    '  <fg=cyan>%s</> <fg=default>(%d)</>',
                    $relative,
                    count($items),
                ));
                foreach ($items as $violation) {
                    $output->writeln(sprintf(
                        '    <fg=red>%d</>  %s',
                        $violation->line,
                        $violation->message,
                    ));
                }
            }
            $output->writeln('');
        }
    }
}
